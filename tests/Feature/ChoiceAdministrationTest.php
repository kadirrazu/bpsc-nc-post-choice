<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,EventCandidate,User};
use App\Services\Choice\ChoiceExportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ChoiceAdministrationTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
    private function fixture(): array {
        $admin=User::factory()->create(['role'=>UserRole::Admin,'is_active'=>true]);
        $event=ChoiceEvent::create(['title'=>'Test Event','post_code'=>'260030','unit'=>'Unit 01','status'=>'PUBLISHED','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$admin->id]);
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $person=$event->candidates()->create(['name'=>'Rahim','fname'=>'Father','mname'=>'Mother','b_date'=>'1997-10-10']);
        $post->applications()->create(['event_candidate_id'=>$person->id,'user'=>'USER01','reg'=>'00001234']);
        $option=$event->choices()->create(['event_post_id'=>$post->id,'code'=>'001','title'=>'First','sort_order'=>1]);
        return [$admin,$event,$person,$option];
    }
    private function submission($person,$option,bool $demo=false): int {
        $id=DB::table('choice_submissions')->insertGetId(['event_candidate_id'=>$person->id,'submitted_choices'=>'001','unselected_choices'=>'','token'=>strtoupper(bin2hex(random_bytes(16))),'status'=>'SUBMITTED','active_slot'=>1,'is_demo'=>$demo,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        DB::table('choice_submission_items')->insert(['choice_submission_id'=>$id,'choice_option_id'=>$option->id,'preference_order'=>1]); return $id;
    }
    public function test_admin_cancellation_retains_history_and_candidate_can_resubmit(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        $this->actingAs($admin)->post(route('choice-submissions.cancel',[$event,$id]),['reason'=>'Candidate requested correction'])->assertRedirect();
        $this->assertDatabaseHas('choice_submissions',['id'=>$id,'status'=>'CANCELLED','active_slot'=>null,'cancelled_by'=>$admin->id]);
        $this->assertDatabaseHas('choice_submission_items',['choice_submission_id'=>$id]);
        $this->post('/candidate/events/'.$event->id.'/sign-in',['user'=>'USER01','birth_date'=>'10101997']);
        $this->post('/candidate/events/'.$event->id.'/review',['choices'=>[$option->id]])->assertOk();
        $nonce=session('candidate_review.'.$event->id)['nonce'];
        $this->post('/candidate/events/'.$event->id.'/submit',['nonce'=>$nonce,'confirm'=>1])->assertRedirect();
        $this->assertDatabaseCount('choice_submissions',2);
        $this->assertDatabaseHas('choice_submissions',['event_candidate_id'=>$person->id,'status'=>'SUBMITTED','active_slot'=>1]);
    }
    public function test_operator_cannot_cancel_and_foreign_event_is_rejected(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        $operator=User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]);
        $this->actingAs($operator)->post(route('choice-submissions.cancel',[$event,$id]),['reason'=>'Test'])->assertForbidden();
        $other=ChoiceEvent::create(['title'=>'Other','status'=>'DRAFT','start_at'=>now(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $this->actingAs($admin)->post(route('choice-submissions.cancel',[$other,$id]),['reason'=>'Test'])->assertNotFound();
        $this->assertDatabaseHas('choice_submissions',['id'=>$id,'status'=>'SUBMITTED']);
    }
    public function test_confirmed_delete_preserves_candidate_and_allows_resubmission(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        $this->actingAs($admin)->get(route('choice-submissions.confirm-delete',[$event,$id]))->assertOk()->assertSee('Confirm Delete');
        $this->delete(route('choice-submissions.destroy',[$event,$id]),[])->assertSessionHasErrors('confirmation');
        $this->delete(route('choice-submissions.destroy',[$event,$id]),['confirmation'=>'DELETE'])->assertRedirect();
        $this->assertDatabaseMissing('choice_submissions',['id'=>$id]);
        $this->assertDatabaseMissing('choice_submission_items',['choice_submission_id'=>$id]);
        $this->assertDatabaseHas('event_candidates',['id'=>$person->id]);
        $this->post('/candidate/events/'.$event->id.'/sign-in',['user'=>'USER01','birth_date'=>'10101997']);
        $this->post('/candidate/events/'.$event->id.'/review',['choices'=>[$option->id]])->assertOk();
        $nonce=session('candidate_review.'.$event->id)['nonce'];
        $this->post('/candidate/events/'.$event->id.'/submit',['nonce'=>$nonce,'confirm'=>1])->assertRedirect();
        $this->assertDatabaseHas('choice_submissions',['event_candidate_id'=>$person->id,'status'=>'SUBMITTED']);
    }
    public function test_clear_all_is_event_scoped_and_requires_confirmation(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        [$otherAdmin,$otherEvent,$otherPerson,$otherOption]=$this->fixture(); $otherId=$this->submission($otherPerson,$otherOption);
        $this->actingAs($admin)->get(route('choice-submissions.confirm-clear',$event))->assertOk()->assertSee('Confirm Clear All');
        $this->delete(route('choice-submissions.clear',$event),[])->assertSessionHasErrors('confirmation');
        $this->delete(route('choice-submissions.clear',$event),['confirmation'=>'CLEAR ALL'])->assertRedirect();
        $this->assertDatabaseMissing('choice_submissions',['id'=>$id]); $this->assertDatabaseHas('choice_submissions',['id'=>$otherId]);
        $this->assertDatabaseHas('event_candidates',['id'=>$person->id]); $this->assertDatabaseHas('choice_options',['id'=>$option->id]);
    }
    public function test_exports_ignore_cancelled_choice_records(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option); $export=new ChoiceExportData;
        $rows=$export->candidates($event); $this->assertSame('00001234',$rows[0]['reg']); $this->assertTrue($rows[0]['submission_status']);
        DB::table('choice_submissions')->where('id',$id)->update(['status'=>'CANCELLED','active_slot'=>null]);
        $rows=$export->candidates($event); $this->assertFalse($rows[0]['submission_status']); $this->assertSame('',$rows[0]['submitted_choices']); $this->assertSame([],$export->candidates($event,true));
    }
    public function test_event_export_and_candidate_xlsx_preserve_records_and_text_ids(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $this->submission($person,$option);
        $this->actingAs($admin)->get(route('choice-submissions.index',$event))->assertOk()->assertSee('Candidate Submissions');
        $response=$this->get(route('choice-exports.candidates',[$event,'xlsx'])); $response->assertOk();
        $path=$response->baseResponse->getFile()->getPathname();
        try {
            $sheet=\PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $this->assertSame('00001234',$sheet->getCell('B2')->getValue());
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,$sheet->getCell('B2')->getDataType());
            $this->assertTrue($sheet->getCell('I2')->getValue());
        } finally { @unlink($path); }
        $response=$this->get(route('choice-exports.record',[$event,'xlsx'])); $response->assertOk();
        $path=$response->baseResponse->getFile()->getPathname();
        try {
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path); $this->assertSame(['Event','Choices'],$book->getSheetNames());
            $this->assertSame('Test Event',$book->getSheetByName('Event')->getCell('B3')->getValue());
            $this->assertSame('001',$book->getSheetByName('Choices')->getCell('B2')->getValue());
        }
        finally { @unlink($path); }
    }
    public function test_operator_cannot_delete_or_clear_and_dbf_download_is_single_file(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        $operator=User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]);
        $this->actingAs($operator)->get(route('choice-submissions.confirm-delete',[$event,$id]))->assertForbidden();
        $this->delete(route('choice-submissions.destroy',[$event,$id]),['confirmation'=>'DELETE'])->assertForbidden();
        $this->delete(route('choice-submissions.clear',$event),['confirmation'=>'CLEAR ALL'])->assertForbidden();
        $response=$this->actingAs($admin)->get(route('choice-exports.candidates',[$event,'dbf'])); $response->assertOk();
        $this->assertStringContainsString('.dbf',$response->headers->get('Content-Disposition'));
        $path=$response->baseResponse->getFile()->getPathname();
        try { $bytes=file_get_contents($path); $this->assertSame(0x03,ord($bytes[0])); }
        finally { @unlink($path); }
    }
    public function test_submission_list_excludes_pending_and_cancelled_candidates(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        $pending=$event->candidates()->create(['name'=>'Never Submitted Candidate','b_date'=>'1997-10-10']);
        $event->posts()->first()->applications()->create(['event_candidate_id'=>$pending->id,'user'=>'PENDING01','reg'=>'00005678']);
        $response=$this->actingAs($admin)->get(route('choice-submissions.index',$event));
        $response->assertOk()->assertSee('Rahim')->assertDontSee('Never Submitted Candidate');
        $this->assertSame(1,$response->viewData('rows')->total());
        DB::table('choice_submissions')->where('id',$id)->update(['status'=>'CANCELLED','active_slot'=>null]);
        $response=$this->get(route('choice-submissions.index',$event));
        $this->assertSame(0,$response->viewData('rows')->total());
    }
    public function test_staff_receipt_download_without_candidate_session_and_event_status_guards(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        $this->mock(\App\Services\Choice\ReceiptQrCode::class,function ($mock) {
            $mock->shouldReceive('dataUri')->once()->withArgs(fn($d)=>$d['user']==='USER01' && $d['reg']==='00001234')->andReturn('data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"></svg>'));
        });
        $this->mock(\App\Services\Choice\SubmissionReceiptPdf::class,function ($mock) {
            $mock->shouldReceive('render')->once()->withArgs(function ($html,$time) {
                $this->assertStringContainsString('Rahim',$html); $this->assertStringContainsString('00001234',$html);
                $this->assertStringContainsString('First',$html); return true;
            })->andReturn('%PDF-1.7 test receipt');
        });
        $this->actingAs($admin)->get(route('choice-submissions.index',$event))->assertOk()->assertSee('Download Receipt');
        $response=$this->get(route('choice-submissions.receipt',[$event,$id]));
        $response->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringContainsString('attachment;',$response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('260030',$response->headers->get('Content-Disposition'));
        $this->assertNull(session('candidate_access.'.$event->id));
        $other=ChoiceEvent::create(['title'=>'Other','status'=>'DRAFT','start_at'=>now(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $this->get(route('choice-submissions.receipt',[$other,$id]))->assertNotFound();
        DB::table('choice_submissions')->where('id',$id)->update(['status'=>'CANCELLED','active_slot'=>null]);
        $this->get(route('choice-submissions.receipt',[$event,$id]))->assertNotFound();
        $viewer=User::factory()->create(['role'=>UserRole::Viewer,'is_active'=>true]);
        $this->actingAs($viewer)->get(route('choice-submissions.receipt',[$event,$id]))->assertForbidden();
    }
    public function test_global_reset_requires_admin_and_explicit_confirmation(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $this->submission($person,$option);
        [$other,$otherEvent,$otherPerson,$otherOption]=$this->fixture(); $this->submission($otherPerson,$otherOption);
        $operator=User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]);
        $this->actingAs($operator)->get(route('choice-data.confirm'))->assertForbidden();
        $this->delete(route('choice-data.destroy'),['confirmation'=>'RESET ALL','acknowledge'=>1])->assertForbidden();
        $this->actingAs($admin)->get(route('choice-data.confirm'))->assertOk()->assertSee('Reset All Choice Data');
        $this->delete(route('choice-data.destroy'),['confirmation'=>'RESET ALL'])->assertSessionHasErrors('acknowledge');
        $this->delete(route('choice-data.destroy'),['confirmation'=>'WRONG','acknowledge'=>1])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('choice_events',2);
        $this->delete(route('choice-data.destroy'),['confirmation'=>'RESET ALL','acknowledge'=>1])->assertRedirect(route('choice-events.index'));
        foreach (['choice_events','event_posts','choice_options','event_candidates','candidate_applications','candidate_imports','choice_submissions','choice_submission_items','choice_audits'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertDatabaseHas('users',['id'=>$admin->id]);
        $this->assertDatabaseHas('users',['id'=>$other->id]);
        $this->assertDatabaseHas('users',['id'=>$operator->id]);
    }
    public function test_event_record_counts_unique_candidates_and_excludes_cancelled_history(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $active=$this->submission($person,$option);
        $extraPost=$event->posts()->create(['post_code'=>'260031','title'=>'Second Post']);
        $extraPost->applications()->create(['event_candidate_id'=>$person->id,'user'=>'USER02','reg'=>'00002345']);
        $cancelledPerson=$event->candidates()->create(['name'=>'Cancelled Candidate','b_date'=>'1997-10-10']);
        $cancelled=$this->submission($cancelledPerson,$option);
        DB::table('choice_submissions')->where('id',$cancelled)->update(['status'=>'CANCELLED','active_slot'=>null]);
        $event->candidates()->create(['name'=>'Pending Candidate','b_date'=>'1997-10-10']);
        [$otherAdmin,$otherEvent,$otherPerson,$otherOption]=$this->fixture(); $this->submission($otherPerson,$otherOption);
        $this->assertSame(['total_candidates'=>3,'submitted_candidates'=>1],(new ChoiceExportData)->summary($event));
        $response=$this->actingAs($admin)->get(route('choice-exports.record',[$event,'xlsx']))->assertOk();
        $path=$response->baseResponse->getFile()->getPathname();
        try {
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $rows=$book->getSheetByName('Event')->toArray(); $metadata=array_column($rows,1,0);
            $this->assertSame('3',$metadata['Total candidates']);
            $this->assertSame(3,$book->getSheetByName('Event')->getCell('B7')->getValue());
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC,$book->getSheetByName('Event')->getCell('B7')->getDataType());
            $this->assertSame('1',$metadata['Submitted candidates']);
            $this->assertSame('260030',$metadata['Post code']);
            $this->assertSame(['Event','Choices'],$book->getSheetNames());
        } finally { @unlink($path); }
        $this->mock(\App\Services\Choice\SubmissionReceiptPdf::class,function ($mock) {
            $mock->shouldReceive('render')->once()->withArgs(function ($html,$stamp,$signature) {
                $this->assertStringContainsString('<th>Total Candidates</th><td>3</td>',$html);
                $this->assertStringContainsString('<th>Submitted Candidates</th><td>1</td>',$html);
                return $signature==="Administrator's Signature";
            })->andReturn('%PDF-1.7 test');
        });
        $this->get(route('choice-exports.record',[$event,'pdf']))->assertOk()->assertHeader('Content-Type','application/pdf');
        DB::table('choice_submissions')->where('id',$active)->update(['status'=>'CANCELLED','active_slot'=>null]);
        $this->assertSame(0,(new ChoiceExportData)->summary($event)['submitted_candidates']);
        $empty=ChoiceEvent::create(['title'=>'Empty Event','status'=>'DRAFT','start_at'=>now(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $this->assertSame(['total_candidates'=>0,'submitted_candidates'=>0],(new ChoiceExportData)->summary($empty));
    }
    public function test_closing_event_blocks_reviewed_submission_without_changing_schedule_or_records(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $end=$event->end_at->toDateTimeString();
        $prefix='/candidate/events/'.$event->id;
        $this->post($prefix.'/sign-in',['user'=>'USER01','birth_date'=>'10101997'])->assertRedirect();
        $this->post($prefix.'/review',['choices'=>[$option->id]])->assertOk();
        $nonce=session('candidate_review.'.$event->id)['nonce'];
        $this->actingAs($admin)->get(route('choice-events.show',$event))->assertOk()->assertSee('Mark as Closed');
        $this->get(route('choice-events.confirm-close',$event))->assertOk()->assertSee('Confirm Close');
        $this->post(route('choice-events.close',$event),[])->assertSessionHasErrors('confirmation');
        $this->assertSame('PUBLISHED',$event->fresh()->status);
        $this->post(route('choice-events.close',$event),['confirmation'=>'CLOSE'])->assertRedirect(route('choice-events.show',$event));
        $this->assertSame('CLOSED',$event->fresh()->status);
        $this->assertSame('CLOSED',$event->fresh()->lifecycle);
        $this->assertSame($end,$event->fresh()->end_at->toDateTimeString());
        $this->assertDatabaseHas('choice_audits',['choice_event_id'=>$event->id,'actor_id'=>$admin->id,'action'=>'EVENT_CLOSED']);
        $this->get('/')->assertOk()->assertDontSee('Test Event');
        $this->get($prefix.'/choices')->assertForbidden();
        $this->post($prefix.'/review',['choices'=>[$option->id]])->assertForbidden();
        $this->post($prefix.'/submit',['nonce'=>$nonce,'confirm'=>1])->assertForbidden();
        $this->assertDatabaseCount('choice_submissions',0);
        $this->assertDatabaseHas('event_candidates',['id'=>$person->id]);
        $this->assertDatabaseHas('choice_options',['id'=>$option->id]);
        $this->post($prefix.'/sign-out');
        $this->post($prefix.'/sign-in',['user'=>'USER01','birth_date'=>'10101997'])->assertForbidden();
        $event->update(['end_at'=>now()->subMinute()]);
        $this->assertSame('CLOSED',$event->fresh()->lifecycle);
    }
    public function test_close_requires_administrator_and_receipt_remains_accessible_after_closing(): void {
        [$admin,$event,$person,$option]=$this->fixture(); $id=$this->submission($person,$option);
        $operator=User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]);
        $this->actingAs($operator)->get(route('choice-events.confirm-close',$event))->assertForbidden();
        $this->post(route('choice-events.close',$event),['confirmation'=>'CLOSE'])->assertForbidden();
        $this->assertSame('PUBLISHED',$event->fresh()->status);
        $this->post('/candidate/events/'.$event->id.'/sign-in',['user'=>'USER01','birth_date'=>'10101997'])->assertRedirect();
        $this->actingAs($admin)->post(route('choice-events.close',$event),['confirmation'=>'CLOSE'])->assertRedirect();
        $this->get('/candidate/events/'.$event->id.'/choices')->assertOk()->assertSee('Choice Submission Receipt');
        $this->assertDatabaseHas('choice_submissions',['id'=>$id,'status'=>'SUBMITTED']);
        $this->post(route('choice-events.close',$event),['confirmation'=>'CLOSE'])->assertForbidden();
        $event->update(['status'=>'DRAFT']);
        $this->post(route('choice-events.close',$event),['confirmation'=>'CLOSE'])->assertForbidden();
        $this->put(route('choice-events.update',$event),['title'=>$event->title,'post_code'=>$event->post_code,'unit'=>$event->unit,'status'=>'CLOSED','start_at'=>$event->start_at->format('Y-m-d\TH:i'),'end_at'=>$event->end_at->format('Y-m-d\TH:i')])->assertRedirect();
        $this->assertSame('CLOSED',$event->fresh()->status);
    }
}
