<?php
namespace Tests\Unit;
use App\Services\Choice\BanglaReceiptPdf;
use Tests\TestCase;
class BanglaReceiptPdfTest extends TestCase {
    public function test_bengali_runs_keep_markup_and_escaped_text(): void {
        $html=(new BanglaReceiptPdf)->fontRuns('<td data-choice-title>Assistant / সহকারী প্রোগ্রামার &amp; &lt;literal&gt;</td>');
        $this->assertStringContainsString('font-family:nikosh',$html);
        $this->assertStringContainsString('সহকারী প্রোগ্রামার',$html);
        $this->assertStringContainsString('Assistant /',$html);
        $this->assertStringContainsString('&lt;literal&gt;',$html);
        $this->assertStringNotContainsString('<literal>',$html);
    }
    public function test_choice_xlsx_preserves_unicode_title_and_leading_zero_code(): void {
        $workbook=new \App\Services\Choice\ChoiceWorkbook;
        $book=$workbook->sample();
        $title='সহকারী প্রোগ্রামার — তথ্য ও যোগাযোগ প্রযুক্তি মন্ত্রণালয়';
        $book->getActiveSheet()->setCellValueExplicit('B2',$title,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $path=tempnam(sys_get_temp_dir(),'bangla-choice-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $rows=$workbook->read($path);
            $this->assertSame($title,$rows[0]['title']);
            $this->assertSame('001',$rows[0]['code']);
        } finally { $book->disconnectWorksheets(); @unlink($path); }
    }
    public function test_non_choice_bengali_and_english_keep_their_original_font(): void {
        $html='<h1>বাংলা ইভেন্ট</h1><button>Continue</button><td data-choice-title>Assistant Programmer</td>';
        $result=(new BanglaReceiptPdf)->fontRuns($html);
        $this->assertStringNotContainsString('font-family:nikosh',$result);
        $this->assertStringContainsString('বাংলা ইভেন্ট',$result);
        $this->assertStringContainsString('Assistant Programmer',$result);
    }
}
