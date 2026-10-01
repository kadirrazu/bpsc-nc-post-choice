<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,EventPost,User};
use App\Services\Choice\{ChoiceEditor,ChoiceWorkbook};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
class ChoiceEditorTest extends TestCase {
    use RefreshDatabase;
    private function setupPost(): array {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        $event=ChoiceEvent::create(['title'=>'Test','post_code'=>'260030','unit'=>'Unit 01','status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id]);
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $this->actingAs($user); return [$event,$post];
    }
    private function row(string $code,int $order,?int $id=null): array { return ['id'=>$id,'code'=>$code,'title'=>'Choice '.$code,'post_count'=>2,'sort_order'=>$order]; }
    public function test_bulk_save_and_code_swap_reorder_keep_ids(): void {
        [$event,$post]=$this->setupPost(); $editor=new ChoiceEditor;
        $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/choices';
        $this->put($url,['revision'=>$editor->revision($post),'rows'=>[$this->row('001',1),$this->row('002',2)]])->assertRedirect();
        $existing=$post->choices()->get(); $one=$existing[0]->id; $two=$existing[1]->id;
        $this->put($url,['revision'=>$editor->revision($post),'rows'=>[$this->row('001',1,$two),$this->row('002',2,$one)]])->assertRedirect();
        $this->assertDatabaseHas('choice_options',['id'=>$two,'code'=>'001','sort_order'=>1]);
        $this->assertDatabaseHas('choice_options',['id'=>$one,'code'=>'002','sort_order'=>2]);
        $this->assertDatabaseCount('choice_options',2);
    }
    public function test_duplicate_codes_and_stale_revision_do_not_partially_save(): void {
        [$event,$post]=$this->setupPost(); $editor=new ChoiceEditor; $revision=$editor->revision($post);
        $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/choices';
        $this->put($url,['revision'=>$revision,'rows'=>[$this->row('ABC',1),$this->row('abc',2)]])->assertSessionHasErrors('choices');
        $this->assertDatabaseCount('choice_options',0);
        $this->put($url,['revision'=>$revision,'rows'=>[$this->row('001',1)]])->assertRedirect();
        $this->put($url,['revision'=>$revision,'rows'=>[$this->row('002',1)]])->assertSessionHasErrors('choices');
        $this->assertDatabaseCount('choice_options',1);
    }
    public function test_other_post_codes_and_published_event_changes_are_rejected(): void {
        [$event,$post]=$this->setupPost(); $editor=new ChoiceEditor;
        $other=$event->posts()->create(['post_code'=>'260031','title'=>'Other']);
        $other->choices()->create(['choice_event_id'=>$event->id,'code'=>'001','title'=>'Other','sort_order'=>1]);
        $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/choices';
        $this->put($url,['revision'=>$editor->revision($post),'rows'=>[$this->row('001',1)]])->assertSessionHasErrors('choices');
        $event->update(['status'=>'PUBLISHED']);
        $this->put($url,['revision'=>$editor->revision($post),'rows'=>[$this->row('002',1)]])->assertSessionHasErrors('choices');
        $this->assertDatabaseCount('choice_options',1);
    }
    public function test_xlsx_sample_roundtrip_preview_and_confirm_once(): void {
        [$event,$post]=$this->setupPost(); $workbook=new ChoiceWorkbook;
        $path=tempnam(sys_get_temp_dir(),'choices'); $book=$workbook->sample();
        try {
            (new Xlsx($book))->save($path); $book->disconnectWorksheets();
            $rows=$workbook->read($path); $this->assertSame('001',$rows[0]['code']);
            $url='/choice-events/'.$event->id.'/posts/'.$post->id.'/choice-import';
            $file=UploadedFile::fake()->createWithContent('choices.xlsx',file_get_contents($path));
            $this->post($url.'/preview',['xlsx'=>$file])->assertOk()->assertSee('Confirm Import');
            $this->assertDatabaseCount('choice_options',0);
            $key='choice_xlsx.'.$event->id.'.'.$post->id; $nonce=session($key)['nonce'];
            $this->post($url.'/confirm',['nonce'=>$nonce])->assertRedirect();
            $this->assertDatabaseCount('choice_options',2);
            $this->post($url.'/confirm',['nonce'=>$nonce])->assertSessionHasErrors('xlsx');
            $this->assertDatabaseCount('choice_options',2);
        } finally { unlink($path); }
    }
    public function test_formulas_are_rejected_without_importing(): void {
        $this->setupPost(); $workbook=new ChoiceWorkbook; $book=$workbook->sample();
        $book->getActiveSheet()->setCellValue('B2','=1+1');
        $path=tempnam(sys_get_temp_dir(),'choices');
        try {
            (new Xlsx($book))->save($path); $book->disconnectWorksheets();
            $this->expectException(\Illuminate\Validation\ValidationException::class);
            $workbook->read($path);
        } finally { unlink($path); }
    }
}
