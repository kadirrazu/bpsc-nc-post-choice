<?php
namespace Tests\Feature;
use App\Models\{ChoiceEvent,EventCandidate,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CandidateSubmissionTest extends TestCase {
    use RefreshDatabase;
    private function setupCandidate(): array {
        $user=User::factory()->create();
        $event=ChoiceEvent::create(['title'=>'Available Test','status'=>'PUBLISHED','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id]);
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $person=EventCandidate::create(['choice_event_id'=>$event->id,'name'=>'Rahim','b_date'=>'1997-10-10']);
        $post->applications()->create(['event_candidate_id'=>$person->id,'user'=>'USER01','reg'=>'00001234']);
        $one=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'001','title'=>'First','sort_order'=>1]);
        $two=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'002','title'=>'Second','sort_order'=>2]);
        return [$event,$person,$one,$two];
    }
    public function test_authentication_requires_only_exact_user_and_eight_digit_birth_date(): void {
        [$event]=$this->setupCandidate(); $url='/candidate/events/'.$event->id.'/sign-in';
        $this->get('/')->assertOk()->assertSee('Submit Choices');
        $this->post($url,['user'=>'USER01','birth_date'=>'101097'])->assertSessionHasErrors('birth_date');
        $this->post($url,['user'=>'user01','birth_date'=>'10101997'])->assertSessionHasErrors('credentials');
        $this->post($url,['user'=>'USER01','birth_date'=>'11101997'])->assertSessionHasErrors('credentials');
        $this->post($url,['user'=>'USER01','birth_date'=>'10101997'])->assertRedirect('/candidate/events/'.$event->id.'/choices');
        $this->get('/candidate/events/'.$event->id.'/choices')->assertOk()->assertSee('Rahim');
    }
    public function test_review_then_submission_keeps_order_token_and_prevents_replay(): void {
        [$event,$person,$one,$two]=$this->setupCandidate(); $prefix='/candidate/events/'.$event->id;
        $this->post($prefix.'/sign-in',['user'=>'USER01','birth_date'=>'10101997'])->assertRedirect();
        $this->post($prefix.'/review',['choices'=>[$two->id,$one->id]])->assertOk()->assertSee('Final Submit');
        $this->assertDatabaseCount('choice_submissions',0);
        $nonce=session('candidate_review.'.$event->id)['nonce'];
        $this->post($prefix.'/submit',['nonce'=>$nonce,'confirm'=>'1'])->assertRedirect();
        $this->assertDatabaseHas('choice_submissions',['event_candidate_id'=>$person->id,'submitted_choices'=>'002|001','active_slot'=>1]);
        $this->assertDatabaseHas('choice_submission_items',['choice_option_id'=>$two->id,'preference_order'=>1]);
        $this->get($prefix.'/choices')->assertOk()->assertSee('Choice Submission Receipt');
        $this->post($prefix.'/submit',['nonce'=>$nonce,'confirm'=>'1'])->assertRedirect();
        $this->assertDatabaseCount('choice_submissions',1);
        $this->post($prefix.'/sign-in',['user'=>'USER01','birth_date'=>'10101997'])->assertRedirect();
        $this->get($prefix.'/choices')->assertOk()->assertSee('Choice Submission Receipt');
    }
    public function test_unauthorized_choices_and_closing_time_are_checked_server_side(): void {
        [$event,$person,$one]=$this->setupCandidate(); $prefix='/candidate/events/'.$event->id;
        $this->get($prefix.'/choices')->assertForbidden();
        $this->post($prefix.'/sign-in',['user'=>'USER01','birth_date'=>'10101997'])->assertRedirect();
        $this->post($prefix.'/review',['choices'=>[999999]])->assertSessionHasErrors('choices');
        $this->post($prefix.'/review',['choices'=>[$one->id,$one->id]])->assertSessionHasErrors('choices.1');
        $this->post($prefix.'/review',['choices'=>[$one->id]])->assertOk();
        $nonce=session('candidate_review.'.$event->id)['nonce'];
        $event->update(['end_at'=>now()->subMinute()]);
        $this->post($prefix.'/submit',['nonce'=>$nonce,'confirm'=>'1'])->assertForbidden();
        $this->assertDatabaseCount('choice_submissions',0);
    }
    public function test_receipt_keeps_submission_identity_ip_and_order(): void {
        [$event,$person,$one,$two]=$this->setupCandidate(); $prefix='/candidate/events/'.$event->id;
        $this->post($prefix.'/sign-in',['user'=>'USER01','birth_date'=>'10101997']);
        $this->post($prefix.'/review',['choices'=>[$two->id,$one->id]]);
        $nonce=session('candidate_review.'.$event->id)['nonce'];
        $this->withServerVariables(['REMOTE_ADDR'=>'203.0.113.7'])->post($prefix.'/submit',['nonce'=>$nonce,'confirm'=>'1'])->assertRedirect();
        $this->assertDatabaseHas('choice_submissions',['event_candidate_id'=>$person->id,'submitted_ip'=>'203.0.113.7']);
        $person->update(['name'=>'Changed Name']);
        $this->get($prefix.'/choices')->assertOk()->assertSee('Rahim')->assertDontSee('Changed Name')->assertSee('00001234')->assertSee('203.0.113.7')->assertSeeInOrder(['Second','First']);
        $this->get($prefix.'/receipt.pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->post($prefix.'/sign-out');
        $this->get($prefix.'/receipt.pdf')->assertForbidden();
    }
}
