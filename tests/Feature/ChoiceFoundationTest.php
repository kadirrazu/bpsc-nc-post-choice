<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,User};
use App\Services\Choice\{BirthDateNormalizer,CandidateCsvReader};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class ChoiceFoundationTest extends TestCase {
    use RefreshDatabase;
    private function staff(): User {
        $user=User::factory()->create();
        $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        return $user;
    }
    private function event(User $user, array $override=[]): ChoiceEvent {
        return ChoiceEvent::create(array_merge(['title'=>'Test event','status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id,'multiple_posts'=>false],$override));
    }
    public function test_birth_dates_use_configurable_pivot_and_reject_invalid_dates(): void {
        config(['choice.birth_year_pivot'=>30]); $normalizer=new BirthDateNormalizer;
        foreach (['101000'=>'2000-10-10','101097'=>'1997-10-10','101005'=>'2005-10-10','20102000'=>'2000-10-20','010130'=>'2030-01-01','010131'=>'1931-01-01'] as $input=>$expected) $this->assertSame($expected,$normalizer->normalize((string)$input));
        $this->expectException(\InvalidArgumentException::class); $normalizer->normalize('310299');
    }
    public function test_public_listing_enforces_status_and_time_window(): void {
        $user=$this->staff();
        $this->event($user,['title'=>'Visible event','status'=>'OPEN']);
        $this->event($user,['title'=>'Expired event','status'=>'OPEN','end_at'=>now()->subMinute()]);
        $this->event($user,['title'=>'Future event','status'=>'OPEN','start_at'=>now()->addMinutes(10)]);
        $this->event($user,['title'=>'Private draft']);
        $this->get('/')->assertOk()->assertSee('Bangladesh Public Service Commission')->assertSee('Visible event')->assertDontSee('Expired event')->assertDontSee('Future event')->assertDontSee('Private draft');
    }
    public function test_viewer_cannot_manage_events(): void {
        $viewer=$this->staff(); $viewer->forceFill(['role'=>UserRole::Viewer])->save();
        $this->actingAs($viewer)->get('/choice-events')->assertForbidden();
    }
    public function test_csv_parser_preserves_leading_zeros_and_rejects_duplicate_identifiers(): void {
        $path=tempnam(sys_get_temp_dir(),'choice');
        try {
            file_put_contents($path,"user,reg,name,fname,mname,b_date\nU01,00123456,Rahim,Father,Mother,101097\nU01,00789012,Karim,Father,Mother,101005\n");
            $result=(new CandidateCsvReader)->read($path);
            $this->assertSame('00123456',$result['rows'][0]['reg']);
            $this->assertSame('1997-10-10',$result['rows'][0]['b_date']);
            $this->assertNotEmpty($result['errors']);
        } finally { unlink($path); }
    }
    public function test_import_requires_preview_then_commits_once_and_preserves_registration(): void {
        $user=$this->staff(); $event=$this->event($user);
        $post=$event->posts()->create(['post_code'=>'101','title'=>'Officer']);
        $prefix='/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates';
        $file=UploadedFile::fake()->createWithContent('candidates.csv',"user,reg,name,fname,mname,b_date\nU01,00123456,Rahim,Father,Mother,101097\n");
        $this->actingAs($user)->post($prefix.'/preview',['file'=>$file])->assertOk()->assertSee('Confirm import');
        $this->assertDatabaseCount('candidate_applications',0);
        $key='choice_import.'.$event->id.'.'.$post->id;
        $nonce=session($key)['nonce'];
        $this->post($prefix.'/confirm',['nonce'=>$nonce])->assertRedirect();
        $this->assertDatabaseHas('candidate_applications',['reg'=>'00123456']);
        $this->assertDatabaseHas('event_candidates',['b_date'=>'1997-10-10']);
        $this->post($prefix.'/confirm',['nonce'=>$nonce])->assertStatus(422);
        $this->assertDatabaseCount('candidate_applications',1);
    }
    public function test_expired_events_are_read_only_and_nested_post_must_match_event(): void {
        $user=$this->staff(); $event=$this->event($user,['end_at'=>now()->subMinute()]);
        $other=$this->event($user); $post=$other->posts()->create(['post_code'=>'101','title'=>'Officer']);
        $this->actingAs($user)->get('/choice-events/'.$event->id.'/edit')->assertForbidden();
        $this->get('/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates')->assertNotFound();
        $this->get('/choice-events?archive=1')->assertOk()->assertSee('Test event');
    }
}
