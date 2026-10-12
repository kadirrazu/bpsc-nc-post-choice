<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,User};
use App\Services\Choice\{MultiplePostMatcher,ChoiceExportData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class MultiplePostWorkflowTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
    private function fixture(): array {
        $admin=User::factory()->create(['role'=>UserRole::Admin,'is_active'=>true]);
        $event=ChoiceEvent::create(['title'=>'Multiple Event','post_code'=>'260000','unit'=>'Unit 01','multiple_posts'=>true,'status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $one=$event->posts()->create(['title'=>'Applied Post A','post_code'=>'260001']);
        $two=$event->posts()->create(['title'=>'Applied Post B','post_code'=>'260002']);
        $one->choices()->create(['choice_event_id'=>$event->id,'code'=>'A01','title'=>'Choice A','sort_order'=>1]);
        $two->choices()->create(['choice_event_id'=>$event->id,'code'=>'B01','title'=>'Choice B','sort_order'=>2]);
        $this->actingAs($admin); return [$admin,$event,$one,$two];
    }
    private function row(array $override=[]): array { return array_merge(['user'=>'USER01','reg'=>'0000000001','name'=>'Rahim Uddin','fname'=>'Abdul Karim','mname'=>'Ayesha Begum','b_date'=>'10101997','ssc_roll'=>'001234','ssc_year'=>'2013','nid'=>'','hsc_roll'=>'','hsc_year'=>''],$override); }
    private function upload($event,$post,array $rows,string $extension='csv') {
        $headers=array_keys($rows[0]);
        if ($extension==='csv') {
            $f=fopen('php://temp','r+');fputcsv($f,$headers,',','"','');foreach ($rows as $row) fputcsv($f,array_values($row),',','"','');rewind($f);$bytes=stream_get_contents($f);fclose($f);
            $file=UploadedFile::fake()->createWithContent('candidates.csv',$bytes);
        } else {
            $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet; $sheet=$book->getActiveSheet();
            foreach ($headers as $i=>$header) $sheet->setCellValueExplicit([$i+1,1],$header,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            foreach ($rows as $r=>$row) foreach (array_values($row) as $i=>$value) $sheet->setCellValueExplicit([$i+1,$r+2],$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $path=tempnam(sys_get_temp_dir(),'multiple'); $writer=$extension==='xlsx' ? new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book):new \PhpOffice\PhpSpreadsheet\Writer\Xls($book);$writer->save($path);
            $file=new UploadedFile($path,'candidates.'.$extension,null,null,true);
        }
        try { return $this->from(route('choice-import.index',[$event,$post]))->post(route('choice-multiple.preview',[$event,$post]),['file'=>$file]); }
        finally { if (isset($path)) @unlink($path); }
    }
    private function pending($event,$post): array { return session('multiple_import.'.$event->id.'.'.$post->id); }
    private function confirm($event,$post) { return $this->post(route('choice-multiple.confirm',[$event,$post]),['nonce'=>$this->pending($event,$post)['nonce'],'confirmation'=>1]); }
    private function decisions($event,$post,array $decisions) { return $this->put(route('choice-multiple.decisions',[$event,$post]),['nonce'=>$this->pending($event,$post)['nonce'],'decisions'=>$decisions]); }
    public function test_edit_post_preserves_imports_links_choices_and_invalidates_old_previews(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);$this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $third=$event->posts()->create(['title'=>'Third','post_code'=>'260004']);$this->upload($event,$third,[$this->row(['user'=>'USER03','reg'=>'0000000003'])]);
        $revision=$this->get(route('choice-posts.edit',[$event,$two]))->assertOk()->viewData('revision');
        $payload=['revision'=>$revision,'post_code'=>'260003','title'=>'Updated Post','organization'=>'Updated Organization','ministry'=>'Updated Ministry'];
        $this->put(route('choice-posts.update',[$event,$two]),$payload)->assertRedirect(route('choice-events.show',$event));
        $this->assertDatabaseHas('event_posts',['id'=>$two->id,'post_code'=>'260003','title'=>'Updated Post']);$this->assertDatabaseCount('event_candidates',1);$this->assertDatabaseCount('candidate_applications',2);$this->assertDatabaseCount('candidate_imports',2);$this->assertDatabaseCount('choice_options',2);
        $this->assertDatabaseHas('choice_audits',['choice_event_id'=>$event->id,'actor_id'=>$admin->id,'action'=>'POST_UPDATED']);
        $this->get(route('choice-multiple.review',[$event,$third]))->assertSessionHasErrors('file');
        $dataset=(new \App\Services\Choice\MultiplePostExport)->dataset($event,null,new ChoiceExportData,true);$this->assertContains('reg_260003',$dataset['columns']);$this->assertSame('0000000002',$dataset['rows'][0]['reg_260003']);
        $revision=$this->get(route('choice-posts.edit',[$event,$two]))->viewData('revision');$payload['revision']=$revision;$payload['post_code']=$one->post_code;$this->put(route('choice-posts.update',[$event,$two]),$payload)->assertSessionHasErrors('post_code');$this->assertSame('260003',$two->fresh()->post_code);
        $event->update(['status'=>'PUBLISHED']);$this->post(route('candidate.authenticate',$event),['user'=>'USER02','birth_date'=>'10101997'])->assertRedirect();$this->get(route('candidate.choices',$event))->assertOk()->assertSee('Updated Post')->assertSee('Choice B');
    }
    public function test_delete_post_removes_its_dataset_and_exclusive_people_but_preserves_shared_people(): void {
        [$admin,$event,$one,$two]=$this->fixture();$extra=$this->row(['user'=>'EXTRA01','reg'=>'0000000009','name'=>'Only First Post','fname'=>'','mname'=>'','b_date'=>'03032000','ssc_roll'=>'999999']);
        $this->upload($event,$one,[$this->row(),$extra]);$this->confirm($event,$one);$this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $response=$this->get(route('choice-posts.confirm-delete',[$event,$one]))->assertOk();$this->assertSame(2,$response->viewData('applications'));$this->assertSame(1,$response->viewData('exclusive'));$this->assertSame(1,$response->viewData('shared'));$revision=$response->viewData('revision');
        $this->delete(route('choice-posts.destroy',[$event,$one]),['revision'=>$revision,'confirmation'=>'NO'])->assertSessionHasErrors('confirmation');$this->assertDatabaseCount('candidate_applications',3);
        $this->delete(route('choice-posts.destroy',[$event,$one]),['revision'=>$revision,'confirmation'=>'DELETE'])->assertRedirect(route('choice-events.show',$event));
        $this->assertDatabaseMissing('event_posts',['id'=>$one->id]);$this->assertDatabaseMissing('event_candidates',['name'=>'Only First Post']);$this->assertDatabaseMissing('candidate_imports',['event_post_id'=>$one->id]);$this->assertDatabaseMissing('choice_options',['event_post_id'=>$one->id]);
        $this->assertDatabaseCount('choice_events',1);$this->assertDatabaseCount('event_candidates',1);$this->assertDatabaseCount('candidate_applications',1);$this->assertDatabaseCount('candidate_imports',1);$this->assertDatabaseCount('choice_options',1);$this->assertDatabaseHas('candidate_applications',['event_post_id'=>$two->id,'user'=>'USER02']);
        $this->assertDatabaseHas('choice_audits',['choice_event_id'=>$event->id,'actor_id'=>$admin->id,'action'=>'POST_DELETED']);
        $summary=(new ChoiceExportData)->summary($event);$this->assertSame(1,$summary['total_candidates']);$this->get(route('choice-posts.confirm-delete',[$event,$one]))->assertNotFound();
        $event->update(['status'=>'PUBLISHED']);$this->post(route('candidate.authenticate',$event),['user'=>'USER02','birth_date'=>'10101997'])->assertRedirect();$this->get(route('candidate.choices',$event))->assertOk()->assertSee('Choice B')->assertDontSee('Choice A');
    }
    public function test_post_crud_is_scoped_to_administrators_current_draft_and_multiple_posts(): void {
        [$admin,$event,$one,$two]=$this->fixture();$other=ChoiceEvent::create(['title'=>'Other','multiple_posts'=>true,'status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);$foreign=$other->posts()->create(['title'=>'Foreign','post_code'=>'999999']);
        foreach (['choice-posts.edit','choice-posts.confirm-delete'] as $route) $this->get(route($route,[$event,$foreign]))->assertNotFound();
        $this->delete(route('choice-posts.destroy',[$event,$foreign]),['revision'=>str_repeat('X',64),'confirmation'=>'DELETE'])->assertNotFound();
        foreach (['PUBLISHED','CLOSED','ARCHIVED','CANCELLED'] as $status) { $event->update(['status'=>$status]);$this->get(route('choice-posts.edit',[$event,$one]))->assertSessionHasErrors('post');$this->delete(route('choice-posts.destroy',[$event,$one]),['revision'=>str_repeat('X',64),'confirmation'=>'DELETE'])->assertSessionHasErrors('post'); }
        $event->update(['status'=>'DRAFT','end_at'=>now()->subMinute()]);$this->get(route('choice-posts.edit',[$event,$one]))->assertSessionHasErrors('post');$event->update(['end_at'=>now()->addDay(),'multiple_posts'=>false]);$this->get(route('choice-posts.edit',[$event,$one]))->assertNotFound();$this->get(route('choice-events.show',$event))->assertOk()->assertDontSee('Edit Post')->assertDontSee('Delete Post');
        $event->update(['multiple_posts'=>true]);$this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]));$this->get(route('choice-posts.edit',[$event,$one]))->assertForbidden();$this->delete(route('choice-posts.destroy',[$event,$one]),['confirmation'=>'DELETE','revision'=>str_repeat('X',64)])->assertForbidden();$this->assertDatabaseCount('event_posts',3);
    }
    public function test_active_or_cancelled_submissions_lock_post_changes_without_deleting_data(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);$person=$event->candidates()->first();$revision=$this->get(route('choice-posts.edit',[$event,$one]))->viewData('revision');
        $id=DB::table('choice_submissions')->insertGetId(['event_candidate_id'=>$person->id,'submitted_choices'=>'A01','token'=>str_repeat('L',32),'status'=>'SUBMITTED','active_slot'=>1,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        foreach (['SUBMITTED','CANCELLED'] as $status) { DB::table('choice_submissions')->where('id',$id)->update(['status'=>$status,'active_slot'=>$status==='SUBMITTED' ? 1 : null]);$this->put(route('choice-posts.update',[$event,$one]),['revision'=>$revision,'post_code'=>'260005','title'=>'Changed'])->assertSessionHasErrors('post');$this->delete(route('choice-posts.destroy',[$event,$one]),['revision'=>$revision,'confirmation'=>'DELETE'])->assertSessionHasErrors('post'); }
        $this->assertDatabaseCount('choice_submissions',1);$this->assertDatabaseCount('event_candidates',1);$this->assertDatabaseCount('candidate_applications',1);$this->assertDatabaseCount('choice_options',2);$this->assertSame('260001',$one->fresh()->post_code);
    }
    public function test_stale_post_forms_and_deleted_post_previews_cannot_commit(): void {
        [$admin,$event,$one,$two]=$this->fixture();$revision=$this->get(route('choice-posts.edit',[$event,$one]))->viewData('revision');$one->update(['title'=>'Concurrent title']);
        $this->put(route('choice-posts.update',[$event,$one]),['revision'=>$revision,'post_code'=>'260005','title'=>'Overwrite'])->assertSessionHasErrors('post');$this->assertSame('Concurrent title',$one->fresh()->title);
        $revision=$this->get(route('choice-posts.confirm-delete',[$event,$one]))->viewData('revision');$one->choices()->create(['choice_event_id'=>$event->id,'code'=>'A02','title'=>'Another choice','sort_order'=>3]);$this->delete(route('choice-posts.destroy',[$event,$one]),['revision'=>$revision,'confirmation'=>'DELETE'])->assertSessionHasErrors('post');$this->assertDatabaseCount('choice_options',3);
        $this->upload($event,$two,[$this->row()]);$nonce=$this->pending($event,$two)['nonce'];$revision=$this->get(route('choice-posts.confirm-delete',[$event,$two]))->viewData('revision');$this->delete(route('choice-posts.destroy',[$event,$two]),['revision'=>$revision,'confirmation'=>'DELETE'])->assertRedirect();
        $this->post(route('choice-multiple.confirm',[$event,$two]),['nonce'=>$nonce,'confirmation'=>1])->assertNotFound();$this->assertDatabaseCount('event_candidates',0);$this->assertDatabaseCount('candidate_applications',0);
    }
    public function test_shared_honorific_and_surname_are_unmatched_even_with_same_dob(): void {
        [$admin,$event,$one,$two]=$this->fixture();
        $this->upload($event,$one,[$this->row(['name'=>'MD. BIPUL HOSSAIN','fname'=>'MD. GOLAP ALI','mname'=>'MST. MEHER BANU'])]);
        $this->confirm($event,$one);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002','name'=>'MD. KAMAL HOSSAIN','fname'=>'MD. JAMAL HOSSAIN','mname'=>'KALPANA BEGUM','ssc_roll'=>'009876','ssc_year'=>'2015'])])->assertRedirect();
        $plan=$this->pending($event,$two)['plan'][0];
        $this->assertSame('new',$plan['kind']); $this->assertSame([],$plan['matches']); $this->assertSame('new',$plan['decision']);
        $this->confirm($event,$two)->assertRedirect();
        $this->assertDatabaseCount('event_candidates',2); $this->assertDatabaseCount('candidate_applications',2);
    }
    public function test_partial_names_require_meaningful_tokens_and_independent_evidence(): void {
        $matcher=new MultiplePostMatcher;
        foreach ([['MD. BIPUL HOSSAIN','MD. KAMAL HOSSAIN'],['MD.','MD.'],['MST. BEGUM','BEGUM'],['HOSSAIN','KAMAL HOSSAIN'],['ALI','JAMAL ALI'],['মোঃ বিপুল হোসেন','মোঃ কামাল হোসেন']] as [$a,$b]) {
            $this->assertFalse($matcher->similar($a,$b),$a.' / '.$b);
        }
        foreach ([['MD. RAHIM UDDIN','Rahim Uddin'],['Rahim Uddin','Rahim Udden'],['মোহাম্মদ রহিম উদ্দিন','রহিম উদ্দিন']] as [$a,$b]) $this->assertTrue($matcher->similar($a,$b),$a.' / '.$b);
        $existing=$this->row(['id'=>1,'b_date'=>'1997-10-10','fname'=>'','mname'=>'']);
        $incoming=$this->row(['name'=>'RAHIM UDDIN','b_date'=>'1998-10-10','ssc_roll'=>'999999','ssc_year'=>'2015','fname'=>'','mname'=>'']);
        $this->assertSame('new',$matcher->plan([$incoming],[$existing])[0]['kind']);
        $incoming['b_date']='1997-10-10';
        $this->assertSame('review',$matcher->plan([$incoming],[$existing])[0]['kind']);
    }
    public function test_exact_four_fields_link_despite_optional_differences_and_preserve_original_rows(): void {
        [$admin,$event,$one,$two]=$this->fixture();
        $this->upload($event,$one,[$this->row()])->assertRedirect(route('choice-multiple.review',[$event,$one]));
        $this->assertDatabaseCount('event_candidates',0); $this->confirm($event,$one)->assertRedirect();
        $id=$event->candidates()->first()->id;
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002','fname'=>'Different Father','mname'=>'','nid'=>'NEW-NID'])],'xlsx')->assertRedirect();
        $plan=$this->pending($event,$two)['plan']; $this->assertSame('exact',$plan[0]['kind']); $this->assertSame('link:'.$id,$plan[0]['decision']);
        $this->get(route('choice-multiple.review',[$event,$two]))->assertOk()->assertSee('Different Father')->assertSee('Abdul Karim');
        $this->confirm($event,$two)->assertRedirect();
        $this->assertDatabaseCount('event_candidates',1);$this->assertDatabaseCount('candidate_applications',2);
        $this->assertDatabaseHas('event_candidates',['id'=>$id,'fname'=>'Abdul Karim','nid'=>'NEW-NID']);
        $original=json_decode($two->applications()->first()->source_identity,true);$this->assertSame('Different Father',$original['fname']);
        $export=(new ChoiceExportData)->candidates($event); $this->assertSame('Different Father',$export[1]['fname']);$this->assertSame('0000000002',$export[1]['reg']);
        $this->assertDatabaseHas('choice_audits',['choice_event_id'=>$event->id,'actor_id'=>$admin->id,'action'=>'MULTIPLE_CANDIDATES_IMPORTED']);
    }
    public function test_partial_names_are_review_only_and_link_requires_saved_decision(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);$id=$event->candidates()->first()->id;
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002','name'=>'RAHIM UDDIN','ssc_year'=>'2014'])])->assertRedirect();
        $this->assertSame('review',$this->pending($event,$two)['plan'][0]['kind']);
        $this->get(route('choice-multiple.review',[$event,$two]))->assertOk()->assertSee('Awaiting Decision');
        $this->confirm($event,$two)->assertSessionHasErrors('review');$this->assertDatabaseCount('candidate_applications',1);
        $this->decisions($event,$two,[0=>'link:'.$id])->assertRedirect();$this->confirm($event,$two)->assertRedirect();
        $this->assertDatabaseCount('event_candidates',1);$this->assertDatabaseCount('candidate_applications',2);
        $this->assertSame('2013',$event->candidates()->first()->ssc_year);
    }
    public function test_partial_review_can_create_separate_candidate_or_skip_and_unicode_is_supported(): void {
        [$admin,$event,$one,$two]=$this->fixture();
        $this->upload($event,$one,[$this->row(['name'=>'মোহাম্মদ রহিম উদ্দিন'])]);$this->confirm($event,$one);
        $rows=[$this->row(['user'=>'USER02','reg'=>'0000000002','name'=>'রহিম উদ্দিন']),$this->row(['user'=>'USER03','reg'=>'0000000003','name'=>'মোহাম্মদ রহিম উদ্দিন','ssc_year'=>'2015'])];
        $this->upload($event,$two,$rows,'xls')->assertRedirect();
        $this->assertSame(['review','review'],array_column($this->pending($event,$two)['plan'],'kind'));
        $this->decisions($event,$two,[0=>'new',1=>'skip'])->assertRedirect();$this->confirm($event,$two)->assertRedirect();
        $this->assertDatabaseCount('event_candidates',2);$this->assertDatabaseCount('candidate_applications',2);
        $this->assertDatabaseMissing('candidate_applications',['user'=>'USER03']);
    }
    public function test_multiple_requires_ssc_but_single_post_import_still_accepts_optional_ssc(): void {
        [$admin,$event,$one,$two]=$this->fixture();
        $this->upload($event,$one,[$this->row(['ssc_roll'=>''])])->assertSessionHasErrors('file');
        $single=ChoiceEvent::create(['title'=>'Single','multiple_posts'=>false,'status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $post=$single->posts()->create(['post_code'=>'260003','title'=>'Single Post']);
        $this->upload($single,$post,[$this->row()])->assertNotFound();
        $f=UploadedFile::fake()->createWithContent('single.csv',"user,reg,name,b_date\nSINGLE01,00001234,Single Person,10101997\n");
        $this->post(route('choice-import.preview',[$single,$post]),['file'=>$f])->assertOk();
        $nonce=session('choice_import.'.$single->id.'.'.$post->id)['nonce'];
        $this->post(route('choice-import.confirm',[$single,$post]),['nonce'=>$nonce])->assertRedirect();
        $this->assertDatabaseHas('event_candidates',['choice_event_id'=>$single->id,'ssc_roll'=>null]);
    }
    public function test_duplicates_ambiguous_login_and_stale_previews_cannot_partially_import(): void {
        [$admin,$event,$one,$two]=$this->fixture(); $this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);
        $this->upload($event,$one,[$this->row()])->assertSessionHasErrors('file');
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002']),$this->row(['user'=>'USER03','reg'=>'0000000003'])])->assertSessionHasErrors('file');
        $this->upload($event,$two,[$this->row(['name'=>'RAHIM UDDIN','reg'=>'0000000002'])]);
        $this->decisions($event,$two,[0=>'new']);$this->confirm($event,$two)->assertSessionHasErrors('review');
        $this->assertDatabaseCount('event_candidates',1);$this->assertDatabaseCount('candidate_applications',1);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);
        $event->candidates()->first()->update(['fname'=>'Changed after preview']);
        $this->confirm($event,$two)->assertSessionHasErrors('file');$this->assertDatabaseCount('candidate_applications',1);
    }
    public function test_same_candidate_cannot_have_two_applications_for_the_same_post(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);
        $this->upload($event,$one,[$this->row(['user'=>'USER02','reg'=>'0000000002'])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('candidate_applications',1);
    }
    public function test_linked_post_login_combines_choices_one_submission_and_unique_admin_list(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $event->update(['status'=>'PUBLISHED']);$prefix='/candidate/events/'.$event->id;
        $this->post($prefix.'/sign-in',['user'=>'USER02','birth_date'=>'10101997'])->assertRedirect();
        $this->get($prefix.'/choices')->assertOk()->assertSee('Applied Post A')->assertSee('Applied Post B')->assertSee('Choice A')->assertSee('Choice B');
        $ids=[$two->choices()->first()->id,$one->choices()->first()->id];
        $this->post($prefix.'/review',['choices'=>$ids])->assertOk();$nonce=session('candidate_review.'.$event->id)['nonce'];
        $this->post($prefix.'/submit',['nonce'=>$nonce,'confirm'=>1])->assertRedirect();
        $this->assertDatabaseHas('choice_submissions',['submitted_choices'=>'B01|A01']);
        $this->post($prefix.'/sign-out');$this->post($prefix.'/sign-in',['user'=>'USER01','birth_date'=>'10101997'])->assertRedirect();
        $this->get($prefix.'/choices')->assertOk()->assertSee('Choice Submission Receipt')->assertSee('USER02');
        $this->assertDatabaseCount('choice_submissions',1);
        $response=$this->get(route('choice-submissions.index',$event))->assertOk()->assertSee('USER01')->assertSee('USER02');$this->assertSame(1,$response->viewData('rows')->total());
        $this->get(route('choice-submissions.index',[$event,'q'=>'USER02']))->assertOk();
        $this->assertCount(2,(new ChoiceExportData)->candidates($event,true));
        $this->upload($event,$two,[$this->row(['user'=>'USER03','reg'=>'0000000003'])])->assertForbidden();
        $operator=User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]);
        $this->actingAs($operator)->get(route('choice-submissions.index',$event))->assertOk()->assertDontSee('Delete Submission');
    }
    public function test_review_target_event_scope_expiry_roles_and_confirmation_are_enforced(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002','name'=>'RAHIM UDDIN'])]);
        $this->decisions($event,$two,[0=>'link:999999'])->assertSessionHasErrors('decisions');
        $this->post(route('choice-multiple.confirm',[$event,$two]),['nonce'=>$this->pending($event,$two)['nonce']])->assertSessionHasErrors('confirmation');
        $nonce=$this->pending($event,$two)['nonce'];$this->post(route('choice-multiple.confirm',[$event,$two]),['nonce'=>'wrong','confirmation'=>1])->assertStatus(422);
        $pending=$this->pending($event,$two);$pending['expires']=now()->subMinute()->timestamp;$this->withSession(['multiple_import.'.$event->id.'.'.$two->id=>$pending])->get(route('choice-multiple.review',[$event,$two]))->assertStatus(422);
        $this->get(route('choice-multiple.sample',[$event,$two]))->assertOk();
        $other=ChoiceEvent::create(['title'=>'Other','status'=>'DRAFT','multiple_posts'=>true,'start_at'=>now(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $this->get(route('choice-multiple.sample',[$other,$two]))->assertNotFound();
        $operator=User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]);
        $this->actingAs($operator)->get(route('choice-multiple.sample',[$event,$two]))->assertForbidden();
        $this->get(route('choice-multiple.review',[$event,$two]))->assertForbidden();
    }
    public function test_multi_dataset_reset_preserves_other_links_and_is_locked_by_submission_history(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $this->upload($event,$two,[$this->row(['user'=>'USER03','reg'=>'0000000003','name'=>'Different Person','b_date'=>'11111998'])]);
        $this->decisions($event,$two,[0=>'new']);
        $this->delete(route('choice-import.reset',[$event,$one]),['confirmation'=>'DELETE'])->assertRedirect();
        $this->assertDatabaseCount('event_candidates',1);$this->assertDatabaseCount('candidate_applications',1);
        $this->confirm($event,$two)->assertSessionHasErrors('file');
        $candidate=$event->candidates()->first();DB::table('choice_submissions')->insert(['event_candidate_id'=>$candidate->id,'submitted_choices'=>'B01','token'=>str_repeat('A',32),'status'=>'CANCELLED','active_slot'=>null,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        $this->delete(route('choice-import.reset',[$event,$two]),['confirmation'=>'DELETE'])->assertSessionHasErrors('dataset');
        $this->assertDatabaseCount('candidate_applications',1);
    }
    public function test_new_exact_matches_do_not_normalize_name_case_punctuation_or_ssc_leading_zeros(): void {
        $matcher=new MultiplePostMatcher; $candidate=['id'=>1]+$this->row(['b_date'=>'1997-10-10']);
        $this->assertSame('exact',$matcher->plan([$this->row(['b_date'=>'1997-10-10'])],[$candidate])[0]['kind']);
        foreach ([['name'=>'RAHIM UDDIN'],['name'=>'Rahim-Uddin'],['ssc_roll'=>'1234'],['ssc_year'=>'2014']] as $change) {
            $plan=$matcher->plan([$this->row(array_merge(['b_date'=>'1997-10-10'],$change))],[$candidate]);
            $this->assertSame('review',$plan[0]['kind']);$this->assertNull($plan[0]['decision']);
        }
        $candidate['fname']=null;$candidate['mname']=null;
        $plan=$matcher->plan([$this->row(['name'=>'Unrelated Name','b_date'=>'1997-10-10','fname'=>'','mname'=>''])],[$candidate]);$this->assertSame('new',$plan[0]['kind']);
    }
    public function test_reviewed_identity_aliases_support_later_exact_imports_and_their_login_dates(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);$id=$event->candidates()->first()->id;
        $variant=$this->row(['user'=>'USER02','reg'=>'0000000002','name'=>'RAHIM UDDIN','b_date'=>'11101997','ssc_year'=>'2014']);
        $this->upload($event,$two,[$variant]);$this->assertSame('review',$this->pending($event,$two)['plan'][0]['kind']);
        $this->decisions($event,$two,[0=>'link:'.$id]);$this->confirm($event,$two)->assertRedirect();
        $third=$event->posts()->create(['post_code'=>'260003','title'=>'Third Post']);
        $this->upload($event,$third,[array_merge($variant,['user'=>'USER03','reg'=>'0000000003'])]);
        $plan=$this->pending($event,$third)['plan'][0];$this->assertSame('exact',$plan['kind']);$this->assertSame('link:'.$id,$plan['decision']);
        $this->confirm($event,$third)->assertRedirect();$this->assertDatabaseCount('event_candidates',1);
        $event->update(['status'=>'PUBLISHED']);
        $this->post('/candidate/events/'.$event->id.'/sign-in',['user'=>'USER02','birth_date'=>'11101997'])->assertRedirect();
        $this->get('/candidate/events/'.$event->id.'/choices')->assertOk()->assertSee('Third Post');
        $event->update(['status'=>'DRAFT']);$fourth=$event->posts()->create(['post_code'=>'260004','title'=>'Fourth Post']);
        $unrelated=$this->row(['user'=>'USER02','reg'=>'0000000004','name'=>'Entirely Different Person','fname'=>'Different Father','mname'=>'Different Mother','b_date'=>'11101997','ssc_roll'=>'009999','ssc_year'=>'2020']);
        $this->upload($event,$fourth,[$unrelated]);$plan=$this->pending($event,$fourth)['plan'][0];
        if ($plan['kind']==='review') $this->decisions($event,$fourth,[0=>'new']);
        $this->confirm($event,$fourth)->assertSessionHasErrors('review');$this->assertDatabaseCount('event_candidates',1);
    }
    public function test_review_pagination_saves_rows_on_each_page_before_atomic_confirmation(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);
        $rows=[];for ($n=0;$n<26;$n++) $rows[]=$this->row(['user'=>sprintf('REVIEW%03d',$n),'reg'=>sprintf('%010d',100+$n),'name'=>'RAHIM UDDIN','ssc_roll'=>(string)(200000+$n)]);
        $this->upload($event,$two,$rows);$this->get(route('choice-multiple.review',[$event,$two]))->assertOk()->assertSee('REVIEW000')->assertDontSee('REVIEW025');
        $this->get(route('choice-multiple.review',[$event,$two,'page'=>2]))->assertOk()->assertSee('REVIEW025');
        $this->decisions($event,$two,array_fill(0,25,'skip'))->assertRedirect();
        $this->confirm($event,$two)->assertSessionHasErrors('review');$this->assertDatabaseCount('candidate_applications',1);
        $this->decisions($event,$two,[25=>'new'])->assertRedirect();$this->confirm($event,$two)->assertRedirect();
        $this->assertDatabaseCount('candidate_applications',2);$this->assertDatabaseHas('candidate_applications',['user'=>'REVIEW025']);
        $this->post(route('choice-multiple.confirm',[$event,$two]),['nonce'=>'replay','confirmation'=>1])->assertStatus(422);
    }
    public function test_ambiguous_exact_identities_require_review_and_supporting_fields_only_suggest_links(): void {
        $matcher=new MultiplePostMatcher;$row=$this->row(['b_date'=>'1997-10-10']);
        $plan=$matcher->plan([$row],[['id'=>1]+$row,['id'=>2]+$row]);
        $this->assertSame('review',$plan[0]['kind']);$this->assertSame([1,2],$plan[0]['matches']);$this->assertNull($plan[0]['decision']);
        $candidate=['id'=>1]+array_merge($row,['nid'=>'NID1234']);
        $different=$this->row(['name'=>'Unrelated Person','fname'=>'','mname'=>'','b_date'=>'1998-12-12','ssc_roll'=>'999999','ssc_year'=>'2016','nid'=>'NID1234']);
        $plan=$matcher->plan([$different],[$candidate]);$this->assertSame('review',$plan[0]['kind']);$this->assertNull($plan[0]['decision']);
    }
    public function test_matched_post_exports_keep_original_rows_reciprocal_links_and_blank_unmatched_values(): void {
        [$admin,$event,$one,$two]=$this->fixture();
        $unrelated=$this->row(['user'=>'UNMATCH01','reg'=>'0000000009','name'=>'Different Person','fname'=>'','mname'=>'','ssc_roll'=>'999999']);
        $this->upload($event,$one,[$this->row(),$unrelated]);$this->confirm($event,$one);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002','fname'=>'Original Other Father'])]);$this->confirm($event,$two);
        foreach ([[$one,'reg_260002','user_260002','0000000002','USER02'],[$two,'reg_260001','user_260001','0000000001','USER01']] as [$post,$regKey,$userKey,$reg,$user]) {
            $response=$this->get(route('choice-exports.matched',[$event,$post,'xlsx']))->assertOk();$path=$response->baseResponse->getFile()->getPathname();
            try {
                $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getActiveSheet();$headers=$sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
                $values=$sheet->rangeToArray('A2:'.$sheet->getHighestColumn().'2')[0];$record=array_combine($headers,$values);
                $this->assertSame($reg,$record[$regKey]);$this->assertSame($user,$record[$userKey]);$this->assertSame('001234',$record['ssc_roll']);
                if ($post->id===$one->id) { $this->assertSame(3,$sheet->getHighestDataRow());$last=array_combine($headers,$sheet->rangeToArray('A3:'.$sheet->getHighestColumn().'3')[0]);$this->assertEmpty($last[$regKey]);$this->assertEmpty($last[$userKey]); }
                else $this->assertSame('Original Other Father',$record['fname']);
                $this->assertStringContainsString($post->post_code,$response->headers->get('Content-Disposition'));$book->disconnectWorksheets();
            } finally { @unlink($path); }
        }
        $response=$this->get(route('choice-exports.matched',[$event,$one,'dbf']))->assertOk();$path=$response->baseResponse->getFile()->getPathname();
        try {
            $bytes=file_get_contents($path);$this->assertSame(3,ord($bytes[0]));$this->assertSame(2,unpack('V',substr($bytes,4,4))[1]);
            $header=unpack('v',substr($bytes,8,2))[1];$length=unpack('v',substr($bytes,10,2))[1];$offset=1;$records=[[],[]];
            for ($p=32;ord($bytes[$p])!==13;$p+=32) { $field=rtrim(substr($bytes,$p,11),"\0");$width=ord($bytes[$p+16]);foreach ([0,1] as $i) $records[$i][$field]=rtrim(substr($bytes,$header+$i*$length+$offset,$width));if (in_array($field,['REG_260002','USR_260002'])) $this->assertSame(10,$width);$offset+=$width; }
            $this->assertSame('0000000002',$records[0]['REG_260002']);$this->assertSame('USER02',$records[0]['USR_260002']);$this->assertSame('10-10-1997',$records[0]['B_DATE']);$this->assertSame('FALSE',$records[0]['SUBMITTED']);$this->assertSame('',$records[1]['REG_260002']);
            $this->assertSame($header+2*$length+1,strlen($bytes));$this->assertMatchesRegularExpression('/\.dbf"?$/',$response->headers->get('Content-Disposition'));
        } finally { @unlink($path); }
        $this->get(route('choice-events.show',$event))->assertOk()->assertSee('Matched Candidate Exports')->assertSee(route('choice-exports.matched',[$event,$one,'xlsx']),false);
        $otherEvent=ChoiceEvent::create(['title'=>'Other','multiple_posts'=>true,'status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $foreign=$otherEvent->posts()->create(['post_code'=>'999999','title'=>'Foreign']);$this->get(route('choice-exports.matched',[$event,$foreign,'xlsx']))->assertNotFound();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]))->get(route('choice-exports.matched',[$event,$one,'dbf']))->assertForbidden();
    }
    public function test_multiple_statistics_count_unique_people_and_each_post_excluding_cancelled_history(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row(),$this->row(['user'=>'USER09','reg'=>'0000000009','name'=>'Another Person','ssc_roll'=>'999999'])]);$this->confirm($event,$one);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $people=$event->candidates()->orderBy('id')->get();
        foreach ([[$people[0]->id,'SUBMITTED',1],[$people[0]->id,'CANCELLED',null],[$people[1]->id,'CANCELLED',null]] as $i=>[$id,$status,$active]) DB::table('choice_submissions')->insert(['event_candidate_id'=>$id,'submitted_choices'=>'A01|B01','token'=>str_pad((string)$i,32,'X'),'status'=>$status,'active_slot'=>$active,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        foreach (['choice-events.show','choice-submissions.index'] as $route) {
            $response=$this->get(route($route,$event))->assertOk()->assertSee('Unique Submissions')->assertSee('Not Submitted');
            $this->assertSame(['total_candidates'=>2,'submitted_candidates'=>1],$response->viewData('summary'));
            $stats=$response->viewData('postSummary');$this->assertSame([2,1],array_column($stats,'total_candidates'));$this->assertSame([1,1],array_column($stats,'submitted_candidates'));$this->assertSame([1,0],array_column($stats,'not_submitted'));
        }
        $response=$this->get(route('choice-exports.record',[$event,'xlsx']))->assertOk();$path=$response->baseResponse->getFile()->getPathname();
        try { $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$this->assertSame(2,$book->getSheetCount());$values=$book->getSheet(0)->toArray(null,true,false);$header=array_search('Applied post',array_column($values,1),true);$this->assertNotFalse($header);$this->assertSame('260001',$values[$header+1][0]);$this->assertSame(2,$values[$header+1][2]);$this->assertSame(1,$values[$header+1][3]);$this->assertSame(1,$values[$header+1][4]);$book->disconnectWorksheets(); } finally { @unlink($path); }
        $response=$this->get(route('choice-exports.record',[$event,'pdf']))->assertOk();$this->assertStringStartsWith('%PDF',$response->getContent());
        if ($qa=getenv('EXPORT_QA_DIR')) file_put_contents($qa.'/multiple-event-record.pdf',$response->getContent());
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]))->get(route('choice-submissions.index',$event))->assertOk()->assertSee('Applied Post Submission Statistics');
    }
    public function test_long_post_codes_have_safe_unique_dbf_aliases(): void {
        [$admin,$event,$one,$two]=$this->fixture();$two->update(['post_code'=>'LONG-POST-CODE']);$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);$this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $dataset=(new \App\Services\Choice\MultiplePostExport)->dataset($event,$one,new ChoiceExportData);
        $this->assertSame('reg_LONG-POST-CODE',$dataset['mapping']['R_P'.$two->id]);$this->assertSame('user_LONG-POST-CODE',$dataset['mapping']['U_P'.$two->id]);
        $response=$this->get(route('choice-exports.matched',[$event,$one,'dbf']))->assertOk();@unlink($response->baseResponse->getFile()->getPathname());
    }
    public function test_combined_exports_sort_by_post_code_then_registration_with_all_matched_columns(): void {
        [$admin,$event,$one,$two]=$this->fixture();
        $two->update(['post_code'=>'260000']); // Created later, but must be exported first.
        $extra=$this->row(['user'=>'EXTRA01','reg'=>'0000000001','name'=>'Different Person','fname'=>'','mname'=>'','b_date'=>'03032000','ssc_roll'=>'999999']);
        $this->upload($event,$one,[$this->row(['reg'=>'0000000009']),$extra]);$this->confirm($event,$one);
        $later=$this->row(['user'=>'EXTRA01','reg'=>'0000000001','name'=>'Unrelated Applicant','fname'=>'','mname'=>'','b_date'=>'04042000','ssc_roll'=>'888888']);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000003']),$later]);$this->confirm($event,$two);
        $dataset=(new \App\Services\Choice\MultiplePostExport)->dataset($event,null,new ChoiceExportData);
        $this->assertSame(['260000','260000','260001','260001'],array_column($dataset['rows'],'applied_post_code'));
        $this->assertSame(['0000000001','0000000003','0000000001','0000000009'],array_column($dataset['rows'],'reg'));
        $this->assertSame(['Unrelated Applicant','Rahim Uddin','Different Person','Rahim Uddin'],array_column($dataset['rows'],'name'));
        $this->assertSame('0000000009',$dataset['rows'][1]['reg_260001']);$this->assertSame('USER01',$dataset['rows'][1]['user_260001']);
        $this->assertSame('0000000003',$dataset['rows'][3]['reg_260000']);$this->assertSame('USER02',$dataset['rows'][3]['user_260000']);
        $this->assertSame('',$dataset['rows'][0]['reg_260001']);$this->assertSame('',$dataset['rows'][2]['reg_260000']);
        foreach (['xlsx','dbf'] as $format) {
            $response=$this->get(route('choice-exports.combined',[$event,$format]))->assertOk();$path=$response->baseResponse->getFile()->getPathname();
            $this->assertMatchesRegularExpression('/combined-matched-candidates-260000-\d{8}_\d{6}\.'.$format.'/',$response->headers->get('Content-Disposition'));
            try {
                if ($format==='xlsx') {
                    $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getActiveSheet();$values=$sheet->toArray(null,true,false);$headers=array_shift($values);$rows=array_map(fn($r)=>array_combine($headers,$r),$values);
                    $this->assertCount(4,$rows);$this->assertSame(['0000000001','0000000003','0000000001','0000000009'],array_column($rows,'reg'));$this->assertSame('0000000009',$rows[1]['reg_260001']);$book->disconnectWorksheets();
                } else {
                    $bytes=file_get_contents($path);$header=unpack('v',substr($bytes,8,2))[1];$length=unpack('v',substr($bytes,10,2))[1];$this->assertSame(4,unpack('V',substr($bytes,4,4))[1]);$offset=1;$rows=array_fill(0,4,[]);
                    for ($p=32;ord($bytes[$p])!==13;$p+=32) { $field=rtrim(substr($bytes,$p,11),"\0");$width=ord($bytes[$p+16]);for ($i=0;$i<4;$i++) $rows[$i][$field]=rtrim(substr($bytes,$header+$i*$length+$offset,$width));$offset+=$width; }
                    $this->assertSame(['260000','260000','260001','260001'],array_column($rows,'POSTCODE'));$this->assertSame(['0000000001','0000000003','0000000001','0000000009'],array_column($rows,'REG'));$this->assertSame('0000000009',$rows[1]['REG_260001']);$this->assertSame('USER02',$rows[3]['USR_260000']);$this->assertSame('',$rows[0]['REG_260001']);$this->assertSame($header+4*$length+1,strlen($bytes));
                }
            } finally { @unlink($path); }
        }
        $this->get(route('choice-events.show',$event))->assertOk()->assertSee('Combined List')->assertSee(route('choice-exports.combined',[$event,'xlsx']),false);
        $this->get(route('choice-exports.combined',[$event,'zip']))->assertNotFound();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]))->get(route('choice-exports.combined',[$event,'dbf']))->assertForbidden();
    }
    public function test_event_list_submitted_count_is_unique_and_includes_archive(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);$this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $person=$event->candidates()->first();
        foreach ([['SUBMITTED',1],['CANCELLED',null]] as $i=>[$status,$slot]) DB::table('choice_submissions')->insert(['event_candidate_id'=>$person->id,'submitted_choices'=>'A01','token'=>str_pad((string)$i,32,'S'),'status'=>$status,'active_slot'=>$slot,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        $response=$this->get(route('choice-events.index'))->assertOk()->assertSee('Event / Post Code')->assertSee('Submitted');$row=$response->viewData('events')->first();$this->assertSame(1,$row->submitted_count);$this->assertSame(1,$row->candidates_count);$this->assertSame(2,$row->posts_count);
        $event->update(['status'=>'ARCHIVED']);$response=$this->get(route('choice-events.index',['archive'=>1]))->assertOk();$this->assertSame(1,$response->viewData('events')->first()->submitted_count);
        $single=ChoiceEvent::create(['title'=>'Single','post_code'=>'260999','multiple_posts'=>false,'status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addDay(),'created_by'=>$admin->id]);
        $this->get(route('choice-exports.combined',[$single,'xlsx']))->assertNotFound();
        $empty=$this->get(route('choice-events.index'))->assertOk();$this->assertSame(0,$empty->viewData('events')->first()->submitted_count);
    }
    public function test_unique_combined_exports_keep_first_post_identity_and_append_only_new_people(): void {
        [$admin,$event,$one,$two]=$this->fixture();$two->update(['post_code'=>'260000']);
        $empty=$this->get(route('choice-exports.combined-unique',[$event,'dbf']))->assertOk();$emptyPath=$empty->baseResponse->getFile()->getPathname();
        try { $this->assertSame(0,unpack('V',substr(file_get_contents($emptyPath),4,4))[1]); } finally { @unlink($emptyPath); }
        $extra=$this->row(['user'=>'EXTRA01','reg'=>'0000000001','name'=>'Different Person','fname'=>'','mname'=>'','b_date'=>'03032000','ssc_roll'=>'999999']);
        $this->upload($event,$one,[$this->row(['reg'=>'0000000009']),$extra]);$this->confirm($event,$one);
        $later=$this->row(['user'=>'EXTRA01','reg'=>'0000000001','name'=>'Unrelated Applicant','fname'=>'','mname'=>'','b_date'=>'04042000','ssc_roll'=>'888888']);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000003','fname'=>'Earlier Post Father']),$later]);$this->confirm($event,$two);
        $third=$event->posts()->create(['post_code'=>'260003','title'=>'Third Post']);
        $new=$this->row(['user'=>'USER04','reg'=>'0000000002','name'=>'Later Only Applicant','fname'=>'','mname'=>'','b_date'=>'05052001','ssc_roll'=>'777777']);
        $this->upload($event,$third,[$this->row(['user'=>'USER03','reg'=>'0000000008']),$new]);$this->confirm($event,$third);
        foreach ([['Rahim Uddin','SUBMITTED',1],['Unrelated Applicant','CANCELLED',null]] as $i=>[$name,$status,$slot]) DB::table('choice_submissions')->insert(['event_candidate_id'=>$event->candidates()->where('name',$name)->first()->id,'submitted_choices'=>'A01|B01','token'=>str_pad((string)$i,32,'U'),'status'=>$status,'active_slot'=>$slot,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        $service=new \App\Services\Choice\MultiplePostExport;$data=new ChoiceExportData;
        $full=$service->dataset($event,null,$data);$unique=$service->dataset($event,null,$data,true);
        $this->assertCount(6,$full['rows']);$this->assertCount(4,$unique['rows']);$this->assertSame(3,count(array_filter($full['rows'],fn($r)=>$r['submission_status'])));$this->assertSame(1,count(array_filter($unique['rows'],fn($r)=>$r['submission_status'])));
        $this->assertSame(['260000','260000','260001','260003'],array_column($unique['rows'],'applied_post_code'));$this->assertSame(['0000000001','0000000003','0000000001','0000000002'],array_column($unique['rows'],'reg'));
        $this->assertSame('Earlier Post Father',$unique['rows'][1]['fname']);$this->assertSame('USER02',$unique['rows'][1]['user']);$this->assertSame('USER01',$unique['rows'][1]['user_260001']);$this->assertSame('USER03',$unique['rows'][1]['user_260003']);
        $this->assertSame('',$unique['rows'][0]['submitted_choices']);$this->assertFalse($unique['rows'][0]['submission_status']);
        $summary=$data->summary($event);$this->assertSame($summary['total_candidates'],count($unique['rows']));$this->assertSame($summary['submitted_candidates'],count(array_filter($unique['rows'],fn($r)=>$r['submission_status'])));
        foreach (['xlsx','dbf'] as $format) {
            $response=$this->get(route('choice-exports.combined-unique',[$event,$format]))->assertOk();$path=$response->baseResponse->getFile()->getPathname();$this->assertMatchesRegularExpression('/combined-unique-candidates-260000-\d{8}_\d{6}\.'.$format.'/',$response->headers->get('Content-Disposition'));
            try {
                if ($format==='xlsx') {
                    $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getActiveSheet();$values=$sheet->toArray(null,true,false);$headers=array_shift($values);$rows=array_map(fn($r)=>array_combine($headers,$r),$values);
                    $this->assertCount(4,$rows);$this->assertSame(['0000000001','0000000003','0000000001','0000000002'],array_column($rows,'reg'));$this->assertSame('0000000008',$rows[1]['reg_260003']);$this->assertSame(1,count(array_filter($rows,fn($r)=>$r['submission_status'])));$book->disconnectWorksheets();
                } else {
                    $bytes=file_get_contents($path);$header=unpack('v',substr($bytes,8,2))[1];$length=unpack('v',substr($bytes,10,2))[1];$this->assertSame(4,unpack('V',substr($bytes,4,4))[1]);$offset=1;$rows=array_fill(0,4,[]);
                    for ($p=32;ord($bytes[$p])!==13;$p+=32) { $field=rtrim(substr($bytes,$p,11),"\0");$width=ord($bytes[$p+16]);for ($i=0;$i<4;$i++) $rows[$i][$field]=rtrim(substr($bytes,$header+$i*$length+$offset,$width));$offset+=$width; }
                    $this->assertSame(['260000','260000','260001','260003'],array_column($rows,'POSTCODE'));$this->assertSame('Earlier Post Father',$rows[1]['FNAME']);$this->assertSame('0000000008',$rows[1]['REG_260003']);$this->assertSame(['FALSE','TRUE','FALSE','FALSE'],array_column($rows,'SUBMITTED'));$this->assertSame($header+4*$length+1,strlen($bytes));
                }
            } finally { @unlink($path); }
        }
        $this->get(route('choice-events.show',$event))->assertOk()->assertSee('Full Combined List')->assertSee('Combined Unique Candidate List');$this->get(route('choice-exports.combined-unique',[$event,'zip']))->assertNotFound();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'is_active'=>true]))->get(route('choice-exports.combined-unique',[$event,'dbf']))->assertForbidden();
    }
    public function test_multi_exports_include_applied_post_code_and_filters_are_event_scoped(): void {
        [$admin,$event,$one,$two]=$this->fixture();$this->upload($event,$one,[$this->row()]);$this->confirm($event,$one);
        $this->upload($event,$two,[$this->row(['user'=>'USER02','reg'=>'0000000002'])]);$this->confirm($event,$two);
        $response=$this->get(route('choice-exports.candidates',[$event,'xlsx','scope'=>'all','post_id'=>$two->id]))->assertOk();
        $path=$response->baseResponse->getFile()->getPathname();
        try {
            $sheet=\PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $this->assertSame('applied_post_code',$sheet->getCell('J1')->getValue());
            $this->assertSame('260002',$sheet->getCell('J2')->getValue());
            $this->assertSame('0000000002',$sheet->getCell('B2')->getValue());
            $this->assertSame(2,$sheet->getHighestDataRow());
            $this->assertStringContainsString('260002',$response->headers->get('Content-Disposition'));
        } finally { @unlink($path); }
        $response=$this->get(route('choice-exports.candidates',[$event,'dbf','scope'=>'all','post_id'=>$two->id]))->assertOk();
        $path=$response->baseResponse->getFile()->getPathname();
        try {
            $bytes=file_get_contents($path);$header=unpack('v',substr($bytes,8,2))[1];$offset=1;$found=false;
            for ($p=32;ord($bytes[$p])!==13;$p+=32) {
                $field=rtrim(substr($bytes,$p,11),"\0");$width=ord($bytes[$p+16]);
                if ($field==='POSTCODE') { $this->assertSame(20,$width);$this->assertSame('260002',rtrim(substr($bytes,$header+$offset,$width)));$found=true; }
                $offset+=$width;
            }
            $this->assertTrue($found);
        } finally { @unlink($path); }
        $this->get(route('choice-exports.candidates',[$event,'xlsx','post_id'=>999999]))->assertNotFound();
        $event->update(['status'=>'PUBLISHED']);$person=$event->candidates()->first();
        DB::table('choice_submissions')->insert(['event_candidate_id'=>$person->id,'submitted_choices'=>'A01|B01','token'=>str_repeat('Z',32),'status'=>'SUBMITTED','active_slot'=>1,'submitted_at'=>now(),'submitted_ip'=>'127.0.0.1','created_at'=>now(),'updated_at'=>now()]);
        $response=$this->get(route('choice-submissions.index',[$event,'post_id'=>$two->id,'q'=>'USER02']))->assertOk();$this->assertSame(1,$response->viewData('rows')->total());
        $this->get(route('choice-submissions.index',[$event,'post_id'=>999999]))->assertNotFound();
    }
}
