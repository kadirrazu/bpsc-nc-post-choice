<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{User,ChoiceEvent,Designation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash};
use Tests\TestCase;
class StaffUserManagementTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); $this->seed(\Database\Seeders\DesignationSeeder::class); }
    private function users(): array {
        $designation=Designation::where('slug','assistant-programmer')->firstOrFail();
        return [User::factory()->create(['role'=>UserRole::Admin,'designation_id'=>$designation->id,'is_active'=>true]),User::factory()->create(['role'=>UserRole::Operator,'designation_id'=>$designation->id,'is_active'=>true]),$designation];
    }
    private function event(User $admin): array {
        $event=ChoiceEvent::create(['title'=>'Permission Test Event','post_code'=>'260030','unit'=>'Unit 01','status'=>'PUBLISHED','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$admin->id]);
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $candidate=$event->candidates()->create(['name'=>'Candidate','b_date'=>'1997-10-10']);
        $post->applications()->create(['event_candidate_id'=>$candidate->id,'user'=>'USER01','reg'=>'00001234']);
        $choice=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'001','title'=>'First','sort_order'=>1]);
        $submission=DB::table('choice_submissions')->insertGetId(['event_candidate_id'=>$candidate->id,'submitted_choices'=>'001','token'=>str_repeat('A',32),'status'=>'SUBMITTED','active_slot'=>1,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        DB::table('choice_submission_items')->insert(['choice_submission_id'=>$submission,'choice_option_id'=>$choice->id,'preference_order'=>1]);
        return [$event,$post,$choice,$submission];
    }
    private function payload(User $user,array $override=[]): array {
        return array_merge(['name'=>$user->name,'email'=>$user->email,'designation_id'=>$user->designation_id,'role'=>$user->role->value,'is_active'=>1],$override);
    }
    public function test_admin_crud_roles_designations_and_password_reset(): void {
        [$admin,$operator,$designation]=$this->users();
        $this->assertDatabaseCount('designations',8);
        $this->actingAs($admin)->get(route('users.create'))->assertOk()->assertSee('Data Entry Operator')->assertSee('Administrator')->assertDontSee('value="viewer"',false);
        $this->post(route('users.store'),['name'=>'New Staff','email'=>'new@example.test','designation_id'=>$designation->id,'role'=>'operator','password'=>'new-password','password_confirmation'=>'new-password','is_active'=>1])->assertRedirect(route('users.index'));
        $new=User::where('email','new@example.test')->firstOrFail();
        $this->assertSame(UserRole::Operator,$new->role); $this->assertSame($designation->id,$new->designation_id); $this->assertTrue(Hash::check('new-password',$new->password));
        $this->put(route('users.update',$new),$this->payload($new,['name'=>'Updated Staff','role'=>'admin','password'=>'changed-password','password_confirmation'=>'changed-password']))->assertRedirect(route('users.index'));
        $this->assertSame(UserRole::Admin,$new->fresh()->role); $this->assertTrue(Hash::check('changed-password',$new->fresh()->password));
        $this->put(route('users.update',$new),$this->payload($new->fresh(),['role'=>'viewer']))->assertSessionHasErrors('role');
        $this->get(route('users.index',['search'=>'Updated Staff']))->assertOk()->assertSee('Updated Staff')->assertDontSee($operator->email);
    }
    public function test_operator_can_read_submission_status_but_cannot_read_admin_pages_or_export(): void {
        [$admin,$operator]=$this->users(); [$event,$post,$choice,$submission]=$this->event($admin);
        $this->actingAs($operator)->get(route('dashboard'))->assertRedirect(route('submission-status.index'));
        $this->get(route('submission-status.index'))->assertOk()->assertSee('Permission Test Event')->assertDontSee($operator->email);
        $this->get(route('choice-submissions.index',$event))->assertOk()->assertSee('USER01')->assertDontSee('Candidate Choice Exports')->assertDontSee('Download Receipt')->assertDontSee('Delete Submission')->assertDontSee('Clear All Submissions');
        foreach ([route('users.index'),route('users.create'),route('users.edit',$admin),route('users.confirm-delete',$admin),route('choice-events.index'),route('choice-events.show',$event),route('choice-events.edit',$event),route('choice-import.index',[$event,$post]),route('choice-exports.record',[$event,'xlsx']),route('choice-exports.candidates',[$event,'dbf']),route('choice-submissions.receipt',[$event,$submission]),route('choice-data.confirm')] as $url) $this->get($url)->assertForbidden();
    }
    public function test_operator_direct_mutations_are_denied_without_changing_data(): void {
        [$admin,$operator]=$this->users(); [$event,$post,$choice,$submission]=$this->event($admin);
        $this->actingAs($operator);
        $this->post(route('users.store'),[])->assertForbidden();
        $this->put(route('users.update',$admin),$this->payload($admin))->assertForbidden();
        $this->delete(route('users.destroy',$admin),['confirmation'=>'DELETE'])->assertForbidden();
        $this->post(route('choice-events.store'),[])->assertForbidden();
        $this->put(route('choice-events.update',$event),[])->assertForbidden();
        $this->delete(route('choice-events.destroy',$event),['confirmation'=>'DELETE'])->assertForbidden();
        $this->post(route('choice-import.preview',[$event,$post]),[])->assertForbidden();
        $this->post(route('choice-import.confirm',[$event,$post]),[])->assertForbidden();
        $this->delete(route('choice-import.reset',[$event,$post]),[])->assertForbidden();
        $this->put(route('choice-editor.save',[$event,$post]),[])->assertForbidden();
        $this->delete(route('choice-options.destroy',[$event,$post,$choice]),[])->assertForbidden();
        $this->post(route('choice-submissions.cancel',[$event,$submission]),['reason'=>'Test'])->assertForbidden();
        $this->delete(route('choice-submissions.destroy',[$event,$submission]),['confirmation'=>'DELETE'])->assertForbidden();
        $this->delete(route('choice-submissions.clear',$event),['confirmation'=>'CLEAR ALL'])->assertForbidden();
        $this->delete(route('choice-data.destroy'),['confirmation'=>'RESET ALL','acknowledge'=>1])->assertForbidden();
        $this->assertDatabaseHas('choice_events',['id'=>$event->id]); $this->assertDatabaseHas('choice_submissions',['id'=>$submission,'status'=>'SUBMITTED']); $this->assertDatabaseHas('users',['id'=>$admin->id,'deleted_at'=>null]);
    }
    public function test_self_profile_and_password_cannot_change_other_user_or_role(): void {
        [$admin,$operator,$designation]=$this->users();
        $this->actingAs($operator)->get(route('staff-profile.edit'))->assertOk();
        $this->put(route('staff-profile.update'),['name'=>'Own Name','email'=>$operator->email,'designation_id'=>$designation->id,'role'=>'admin'])->assertSessionHasErrors('role');
        $this->put(route('staff-profile.update'),['name'=>'Own Name','email'=>$operator->email,'designation_id'=>$designation->id,'user_id'=>$admin->id])->assertSessionHasErrors('user_id');
        $this->put(route('staff-profile.update'),['name'=>'Own Name','email'=>'own@example.test','designation_id'=>$designation->id])->assertRedirect(route('staff-profile.edit'));
        $this->assertSame('Own Name',$operator->fresh()->name); $this->assertSame(UserRole::Operator,$operator->fresh()->role); $this->assertSame($admin->name,$admin->fresh()->name);
        $this->put(route('staff-profile.update-password'),['current_password'=>'wrong','password'=>'safe-password','password_confirmation'=>'safe-password'])->assertSessionHasErrors('current_password');
        $this->put(route('staff-profile.update-password'),['current_password'=>'password','password'=>'safe-password','password_confirmation'=>'safe-password'])->assertRedirect(route('staff-profile.password'));
        $this->assertTrue(Hash::check('safe-password',$operator->fresh()->password)); $this->assertTrue(Hash::check('password',$admin->fresh()->password));
        $this->actingAs($admin)->get(route('staff-profile.password'))->assertOk();
    }
    public function test_deleted_account_cannot_log_in_or_continue_session_and_history_remains(): void {
        [$admin,$operator]=$this->users(); [$event]=$this->event($operator);
        DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$operator->id,'action'=>'TEST','details'=>'{}','created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($admin)->get(route('users.confirm-delete',$operator))->assertOk();
        $this->delete(route('users.destroy',$operator),[])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('users',['id'=>$operator->id,'deleted_at'=>null]);
        $this->delete(route('users.destroy',$operator),['confirmation'=>'DELETE'])->assertRedirect(route('users.index'));
        $this->assertSoftDeleted('users',['id'=>$operator->id]); $this->assertNull(User::find($operator->id));
        $this->assertDatabaseHas('choice_events',['id'=>$event->id,'created_by'=>$operator->id]); $this->assertDatabaseHas('choice_audits',['actor_id'=>$operator->id]);
        $this->actingAs($operator)->get(route('staff-profile.edit'))->assertRedirect(route('login')); $this->assertGuest();
        $this->post(route('login'),['email'=>$operator->email,'password'=>'password'])->assertSessionHasErrors('email'); $this->assertGuest();
    }
    public function test_inactive_accounts_and_self_deletion_are_blocked(): void {
        [$admin,$operator]=$this->users();
        $this->actingAs($admin)->delete(route('users.destroy',$admin),['confirmation'=>'DELETE'])->assertSessionHasErrors('confirmation');
        $this->put(route('users.update',$admin),$this->payload($admin,['role'=>'operator']))->assertSessionHasErrors('role');
        $this->put(route('users.update',$operator),$this->payload($operator,['is_active'=>0]))->assertRedirect();
        $this->actingAs($operator)->get(route('submission-status.index'))->assertRedirect(route('login'));
        $this->post(route('login'),['email'=>$operator->email,'password'=>'password'])->assertSessionHasErrors('email'); $this->assertGuest();
    }
    public function test_active_login_tracks_time_and_admin_creation_preserves_role(): void {
        [$admin,$operator,$designation]=$this->users();
        $this->post(route('login'),['email'=>$operator->email,'password'=>'password'])->assertRedirect();
        $this->assertAuthenticatedAs($operator); $this->assertNotNull($operator->fresh()->last_login_at);
        $this->get(route('dashboard'))->assertRedirect(route('submission-status.index'));
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login'),['email'=>$admin->email,'password'=>'password'])->assertRedirect();
        $this->assertAuthenticatedAs($admin); $this->get(route('dashboard'))->assertOk();
        $this->post(route('users.store'),['name'=>'Second Administrator','email'=>'second-admin@example.test','designation_id'=>$designation->id,'role'=>'admin','password'=>'admin-password','password_confirmation'=>'admin-password','is_active'=>1])->assertRedirect(route('users.index'));
        $this->assertSame(UserRole::Admin,User::where('email','second-admin@example.test')->firstOrFail()->role);
    }
}
