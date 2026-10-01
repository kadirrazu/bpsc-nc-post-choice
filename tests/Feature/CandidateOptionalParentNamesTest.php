<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,User};
use App\Services\Choice\{CandidateCsvReader,CandidateExcelReader};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
class CandidateOptionalParentNamesTest extends TestCase {
    use RefreshDatabase;
    public function test_csv_without_parent_columns_previews_and_imports(): void {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        $event=ChoiceEvent::create(['title'=>'Test','status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id]);
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $prefix='/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates';
        $file=UploadedFile::fake()->createWithContent('candidates.csv',"user,reg,name,b_date\nU1,0001,Rahim,101097\n");
        $this->actingAs($user)->post($prefix.'/preview',['file'=>$file])->assertOk()->assertSee('Confirm import');
        $nonce=session('choice_import.'.$event->id.'.'.$post->id)['nonce'];
        $this->post($prefix.'/confirm',['nonce'=>$nonce])->assertRedirect();
        $this->assertDatabaseHas('event_candidates',['name'=>'Rahim','fname'=>null,'mname'=>null]);
    }
    public function test_blank_parent_columns_are_valid(): void {
        $reader=new CandidateCsvReader;
        $result=$reader->validateRecords(['user','reg','name','b_date','fname','mname'],[['line'=>2,'values'=>['U1','0001','Rahim','101097','',''],'errors'=>[]]]);
        $this->assertSame([],$result['errors']);
        $this->assertNull($result['rows'][0]['fname']); $this->assertNull($result['rows'][0]['mname']);
    }
    public function test_excel_can_omit_parent_columns(): void {
        $reader=new CandidateExcelReader; $book=$reader->sample();
        $book->getActiveSheet()->removeColumn('D',2); $path=tempnam(sys_get_temp_dir(),'candidate');
        try {
            (new Xlsx($book))->save($path); $book->disconnectWorksheets();
            $result=$reader->read($path,'xlsx');
            $this->assertSame([],$result['errors']); $this->assertNull($result['rows'][0]['fname']); $this->assertNull($result['rows'][0]['mname']);
        } finally { unlink($path); }
    }
}
