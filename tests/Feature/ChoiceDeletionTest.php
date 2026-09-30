<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,EventCandidate,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ChoiceDeletionTest extends TestCase {
    use RefreshDatabase;
    private function event(User $user,string $title): ChoiceEvent {
        return ChoiceEvent::create(['title'=>$title,'post_code'=>'260030','unit'=>'Unit 01','status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id]);
    }
    public function test_event_deletion_is_confirmed_and_scoped_to_its_data(): void {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        $event=$this->event($user,'Delete me'); $keep=$this->event($user,'Keep me');
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $choice=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'101','title'=>'Choice','sort_order'=>1]);
        $candidate=EventCandidate::create(['choice_event_id'=>$event->id,'name'=>'Rahim','fname'=>'Father','mname'=>'Mother','b_date'=>'1997-10-10']);
        $post->applications()->create(['event_candidate_id'=>$candidate->id,'user'=>'U01','reg'=>'00001']);
        $submissionId=DB::table('choice_submissions')->insertGetId(['event_candidate_id'=>$candidate->id,'submitted_choices'=>'101','token'=>'TOKEN','submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        DB::table('choice_submission_items')->insert(['choice_submission_id'=>$submissionId,'choice_option_id'=>$choice->id,'preference_order'=>1]);
        DB::table('candidate_imports')->insert(['event_post_id'=>$post->id,'filename'=>'test.csv','row_count'=>1,'imported_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$user->id,'action'=>'TEST','created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($user)->delete('/choice-events/'.$event->id,['confirmation'=>'wrong'])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('choice_events',['id'=>$event->id]);
        $this->delete('/choice-events/'.$event->id,['confirmation'=>'DELETE'])->assertRedirect('/choice-events');
        $this->assertDatabaseHas('choice_events',['id'=>$keep->id]);
        foreach (['event_posts','choice_options','event_candidates','candidate_applications','choice_submissions','choice_submission_items','candidate_imports','choice_audits'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertDatabaseHas('users',['id'=>$user->id]);
    }
    public function test_operator_cannot_delete_event(): void {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Operator,'is_active'=>true])->save();
        $event=$this->event($user,'Keep');
        $this->actingAs($user)->delete('/choice-events/'.$event->id,['confirmation'=>'DELETE'])->assertForbidden();
        $this->assertDatabaseHas('choice_events',['id'=>$event->id]);
    }
    public function test_choice_deletion_requires_correct_event_and_preserves_other_choices(): void {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        $event=$this->event($user,'One'); $other=$this->event($user,'Two');
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $choice=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'101','title'=>'Choice','sort_order'=>1]);
        $keep=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'102','title'=>'Keep','sort_order'=>2]);
        $this->actingAs($user)->delete('/choice-events/'.$other->id.'/posts/'.$post->id.'/choices/'.$choice->id,['confirmation'=>'DELETE'])->assertNotFound();
        $this->delete('/choice-events/'.$event->id.'/posts/'.$post->id.'/choices/'.$choice->id,['confirmation'=>'DELETE'])->assertRedirect();
        $this->assertDatabaseMissing('choice_options',['id'=>$choice->id]);
        $this->assertDatabaseHas('choice_options',['id'=>$keep->id]);
    }
}
