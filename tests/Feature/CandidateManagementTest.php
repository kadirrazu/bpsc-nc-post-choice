<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,EventCandidate,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class CandidateManagementTest extends TestCase {
    use RefreshDatabase;
    private function setupPost(): array {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        $event=ChoiceEvent::create(['title'=>'Test Event','status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id]);
        $post=$event->posts()->create(['post_code'=>'101','title'=>'Post One']);
        $this->actingAs($user); return [$user,$event,$post];
    }
    private function candidate($event,$post,string $reg,string $name,string $district='Dhaka',bool $parents=true) {
        $person=EventCandidate::create(['choice_event_id'=>$event->id,'name'=>$name,'fname'=>$parents ? 'Father' : null,'mname'=>$parents ? 'Mother' : null,'b_date'=>'1997-10-10']);
        $post->applications()->create(['event_candidate_id'=>$person->id,'user'=>'U'.$reg,'reg'=>$reg,'dist_name'=>$district]);
        return $person;
    }
    public function test_search_filters_summary_and_pagination_are_post_scoped(): void {
        [$user,$event,$post]=$this->setupPost();
        $one=$this->candidate($event,$post,'0001','Rahim');
        $this->candidate($event,$post,'0002','Karim','Chattogram',false);
        DB::table('choice_submissions')->insert(['event_candidate_id'=>$one->id,'submitted_choices'=>'101','token'=>'TOKEN','submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        $other=$event->posts()->create(['post_code'=>'102','title'=>'Other']);
        $this->candidate($event,$other,'0003','Outside Person');
        $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates';
        $this->get($url)->assertOk()->assertSee('Back to Event')->assertSee('Serial')->assertSee('Total Candidates')->assertDontSee('Outside Person')->assertViewHas('summary',fn($s)=>$s['total']===2 && $s['submitted']===1 && $s['not_submitted']===1);
        $this->get($url.'?search=0001')->assertOk()->assertViewHas('applications',fn($p)=>$p->total()===1 && $p[0]->reg==='0001');
        $this->get($url.'?district=Chattogram&parent_names=missing&submission_status=not_submitted')->assertOk()->assertViewHas('applications',fn($p)=>$p->total()===1 && $p[0]->reg==='0002');
        for ($i=4;$i<=30;$i++) $this->candidate($event,$post,sprintf('%04d',$i),'Extra '.$i);
        $this->get($url.'?search=Extra&page=2')->assertOk()->assertSee('Candidate pages')->assertSee('page-link')->assertViewHas('applications',fn($p)=>$p->firstItem()===26 && $p->total()===27);
    }
    public function test_reset_deletes_selected_dataset_and_submission_but_keeps_other_post_and_choices(): void {
        [$user,$event,$post]=$this->setupPost();
        $person=$this->candidate($event,$post,'0001','Delete Me');
        $choice=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'101','title'=>'Choice','sort_order'=>1]);
        $sid=DB::table('choice_submissions')->insertGetId(['event_candidate_id'=>$person->id,'submitted_choices'=>'101','token'=>'TOKEN','submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        DB::table('choice_submission_items')->insert(['choice_submission_id'=>$sid,'choice_option_id'=>$choice->id,'preference_order'=>1]);
        DB::table('candidate_imports')->insert(['event_post_id'=>$post->id,'filename'=>'test.csv','row_count'=>1,'imported_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
        $other=$event->posts()->create(['post_code'=>'102','title'=>'Other']);
        $keep=$this->candidate($event,$other,'0002','Keep Me');
        $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates';
        $this->delete($url,['confirmation'=>'wrong'])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('event_candidates',['id'=>$person->id]);
        $this->delete($url,['confirmation'=>'DELETE'])->assertRedirect($url);
        $this->assertDatabaseMissing('event_candidates',['id'=>$person->id]);
        $this->assertDatabaseHas('event_candidates',['id'=>$keep->id]);
        $this->assertDatabaseHas('choice_options',['id'=>$choice->id]);
        $this->assertDatabaseCount('choice_submissions',0); $this->assertDatabaseCount('choice_submission_items',0); $this->assertDatabaseCount('candidate_imports',0);
        $this->assertDatabaseHas('choice_audits',['action'=>'CANDIDATE_DATASET_RESET']);
    }
    public function test_shared_person_is_preserved_and_operator_cannot_reset(): void {
        [$user,$event,$post]=$this->setupPost();
        $person=$this->candidate($event,$post,'0001','Shared');
        $other=$event->posts()->create(['post_code'=>'102','title'=>'Other']);
        $other->applications()->create(['event_candidate_id'=>$person->id,'user'=>'OTHER','reg'=>'0002']);
        $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates';
        $user->forceFill(['role'=>UserRole::Operator])->save();
        $this->delete($url,['confirmation'=>'DELETE'])->assertForbidden();
        $user->forceFill(['role'=>UserRole::Admin])->save();
        $this->delete($url,['confirmation'=>'DELETE'])->assertRedirect();
        $this->assertDatabaseHas('event_candidates',['id'=>$person->id]);
        $this->assertDatabaseCount('candidate_applications',1);
    }
    public function test_reset_invalidates_previews_from_another_session_and_rejects_published_reset(): void {
        [$user,$event,$post]=$this->setupPost();
        $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates';
        $file=UploadedFile::fake()->createWithContent('test.csv',"user,reg,name,b_date\nU01,0001,Rahim,101097\n");
        $this->post($url.'/preview',['file'=>$file])->assertOk();
        $key='choice_import.'.$event->id.'.'.$post->id; $old=session($key);
        $this->delete($url,['confirmation'=>'DELETE'])->assertRedirect();
        $this->withSession([$key=>$old])->post($url.'/confirm',['nonce'=>$old['nonce']])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('candidate_applications',0);
        $event->update(['status'=>'PUBLISHED']);
        $this->delete($url,['confirmation'=>'DELETE'])->assertSessionHasErrors('dataset');
    }
}
