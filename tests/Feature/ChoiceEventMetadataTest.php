<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ChoiceEventMetadataTest extends TestCase {
    use RefreshDatabase;
    private function staff(): User {
        $user=User::factory()->create();
        $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        return $user;
    }
    private function payload(array $overrides=[]): array {
        return array_merge(['title'=>'Assistant Programmer of Multiple Ministry','post_code'=>'260030','unit'=>'Unit 01','status'=>'DRAFT','start_at'=>now()->subHour()->format('Y-m-d\TH:i'),'end_at'=>now()->addHour()->format('Y-m-d\TH:i')],$overrides);
    }
    public function test_create_form_has_fields_and_all_unit_options(): void {
        $response=$this->actingAs($this->staff())->get('/choice-events/create')->assertOk();
        foreach (['Post code','Unit 01','Unit 20','Non Cadre (Exam)','Published','Archived','Cancelled'] as $label) $response->assertSee($label);
    }
    public function test_metadata_is_saved_and_invalid_unit_or_status_rejected(): void {
        $this->actingAs($this->staff())->post('/choice-events',$this->payload())->assertRedirect();
        $this->assertDatabaseHas('choice_events',['post_code'=>'260030','unit'=>'Unit 01','status'=>'DRAFT']);
        $this->post('/choice-events',$this->payload(['unit'=>'Unit 21']))->assertSessionHasErrors('unit');
        $this->post('/choice-events',$this->payload(['status'=>'OPEN']))->assertSessionHasErrors('status');
    }
    public function test_only_published_events_are_public_and_closed_statuses_are_in_archive(): void {
        $user=$this->staff();
        foreach (['DRAFT','PUBLISHED','ARCHIVED','CANCELLED'] as $status) {
            ChoiceEvent::create($this->payload(['title'=>'Event '.$status,'status'=>$status])+['created_by'=>$user->id]);
        }
        $this->get('/')->assertOk()->assertSee('Event PUBLISHED')->assertDontSee('Event DRAFT')->assertDontSee('Event ARCHIVED')->assertDontSee('Event CANCELLED');
        $this->actingAs($user)->get('/choice-events?archive=1')->assertOk()->assertSee('Event ARCHIVED')->assertSee('Event CANCELLED')->assertDontSee('Event PUBLISHED');
    }
}
