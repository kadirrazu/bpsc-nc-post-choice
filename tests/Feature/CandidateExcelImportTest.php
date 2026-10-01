<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,User};
use App\Services\Choice\CandidateExcelReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\{Writer\Xlsx,Writer\Xls};
use Tests\TestCase;
class CandidateExcelImportTest extends TestCase {
    use RefreshDatabase;
    public function test_xls_and_xlsx_preserve_identifiers_and_use_preview_confirm(): void {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        $this->actingAs($user);
        foreach (['xls','xlsx'] as $format) {
            $event=ChoiceEvent::create(['title'=>'Excel '.$format,'status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id]);
            $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
            $reader=new CandidateExcelReader; $book=$reader->sample(); $path=tempnam(sys_get_temp_dir(),'candidate');
            try {
                $writer=$format==='xls' ? new Xls($book) : new Xlsx($book); $writer->save($path); $book->disconnectWorksheets();
                $result=$reader->read($path,$format);
                $this->assertSame([],$result['errors']);
                $this->assertSame('0012345678',$result['rows'][0]['reg']);
                $this->assertSame('001234',$result['rows'][0]['ssc_roll']);
                $this->assertSame('1997-10-10',$result['rows'][0]['b_date']);
                $prefix='/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates';
                $count=\App\Models\CandidateApplication::count();
                $file=UploadedFile::fake()->createWithContent('candidates.'.$format,file_get_contents($path));
                $this->post($prefix.'/preview',['file'=>$file])->assertOk()->assertSee('Confirm import');
                $this->assertDatabaseCount('candidate_applications',$count);
                $nonce=session('choice_import.'.$event->id.'.'.$post->id)['nonce'];
                $this->post($prefix.'/confirm',['nonce'=>$nonce])->assertRedirect();
                $this->assertDatabaseHas('candidate_applications',['event_post_id'=>$post->id,'reg'=>'0012345678']);
            } finally { unlink($path); }
        }
    }
    public function test_excel_errors_report_real_row_and_missing_optional_columns_are_allowed(): void {
        $reader=new CandidateExcelReader; $book=$reader->sample(); $sheet=$book->getActiveSheet();
        $sheet->removeColumn('G',11); $sheet->setCellValue('F2','310299');
        $sheet->setCellValue('A4','U2'); $sheet->setCellValue('B4','00002');
        $sheet->setCellValue('C4','Second'); $sheet->setCellValue('D4','Father');
        $sheet->setCellValue('E4','Mother'); $sheet->setCellValue('F4','101005');
        $path=tempnam(sys_get_temp_dir(),'candidate');
        try {
            (new Xlsx($book))->save($path); $book->disconnectWorksheets();
            $result=$reader->read($path,'xlsx');
            $this->assertCount(2,$result['rows']); $this->assertSame([2,4],$result['row_numbers']);
            $this->assertStringContainsString('Row 2: Birth date is invalid.',implode(' ',$result['errors']));
            $this->assertSame('2005-10-10',$result['rows'][1]['b_date']);
        } finally { unlink($path); }
    }
    public function test_formula_cells_are_validation_errors(): void {
        $reader=new CandidateExcelReader; $book=$reader->sample();
        $book->getActiveSheet()->setCellValue('C2','=1+1'); $path=tempnam(sys_get_temp_dir(),'candidate');
        try {
            (new Xlsx($book))->save($path); $book->disconnectWorksheets();
            $result=$reader->read($path,'xlsx');
            $this->assertStringContainsString('Formulas are not allowed',implode(' ',$result['errors']));
        } finally { unlink($path); }
    }
    public function test_candidate_page_has_excel_sample_and_accepts_all_formats(): void {
        $user=User::factory()->create(); $user->forceFill(['role'=>UserRole::Admin,'is_active'=>true])->save();
        $event=ChoiceEvent::create(['title'=>'Test','status'=>'DRAFT','start_at'=>now()->subHour(),'end_at'=>now()->addHour(),'created_by'=>$user->id]);
        $post=$event->posts()->create(['post_code'=>'260030','title'=>'Officer']);
        $this->actingAs($user)->get('/choice-events/'.$event->id.'/posts/'.$post->id.'/candidates')->assertOk()->assertSee('Download Sample Excel (XLSX)')->assertSee('.csv,.xls,.xlsx',false);
        $this->get('/candidate-import-template?format=xlsx')->assertOk()->assertHeader('content-type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
