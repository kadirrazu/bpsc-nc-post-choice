<?php
namespace Tests\Unit;
use App\Services\Choice\SubmissionDbf;
use Tests\TestCase;
class SubmissionDbfTest extends TestCase {
    public function test_normalized_widths_birth_date_text_and_true_false_values(): void {
        $path=tempnam(sys_get_temp_dir(),'dbf-test-');
        try {
            (new SubmissionDbf)->save($path,[['user'=>'00001','reg'=>'00001234','name'=>'Rahim','fname'=>'Father','mname'=>'Mother','b_date'=>'1997-10-10','submitted_choices'=>'003|001|002','unselected_choices'=>'004|005','submission_status'=>true],['user'=>'USER02','reg'=>'00001235','name'=>'Pending','b_date'=>'2000-01-02','submission_status'=>false]]);
            $bytes=file_get_contents($path); $this->assertSame(0x03,ord($bytes[0]));
            $header=unpack('Vcount/vheader/vrecord',substr($bytes,4,8)); $this->assertSame(2,$header['count']);
            $fields=[]; $offset=1;
            for ($i=0;$i<9;$i++) {
                $descriptor=substr($bytes,32+$i*32,32); $name=rtrim(substr($descriptor,0,11),"\0"); $width=ord($descriptor[16]);
                $this->assertSame('C',$descriptor[11]); $fields[$name]=['offset'=>$offset,'width'=>$width]; $offset+=$width;
            }
            foreach (['USER'=>10,'REG'=>10,'NAME'=>50,'FNAME'=>50,'MNAME'=>50,'B_DATE'=>10,'SUBCHOICES'=>50,'UNSELECTED'=>50,'SUBMITTED'=>5] as $name=>$width) $this->assertSame($width,$fields[$name]['width']);
            $record=substr($bytes,$header['header'],$header['record']);
            foreach (['REG'=>'00001234','B_DATE'=>'10-10-1997','SUBCHOICES'=>'003|001|002','UNSELECTED'=>'004|005','SUBMITTED'=>'TRUE'] as $field=>$value) $this->assertSame($value,trim(substr($record,$fields[$field]['offset'],$fields[$field]['width'])));
            $second=substr($bytes,$header['header']+$header['record'],$header['record']);
            $this->assertSame('FALSE',substr($second,$fields['SUBMITTED']['offset'],5));
            $this->assertSame($header['header']+2*$header['record']+1,strlen($bytes));
        } finally { @unlink($path); }
    }
    public function test_choice_width_expands_for_longer_lists(): void {
        $path=tempnam(sys_get_temp_dir(),'dbf-test-');
        try {
            $choices=implode('|',range(260001,260020));
            (new SubmissionDbf)->save($path,[['submitted_choices'=>$choices,'submission_status'=>true]]);
            $bytes=file_get_contents($path); $width=ord($bytes[32+6*32+16]);
            $this->assertGreaterThanOrEqual(strlen($choices),$width); $this->assertLessThan(254,$width);
            $header=unpack('vheader',substr($bytes,8,2)); $this->assertSame($choices,trim(substr($bytes,$header['header']+181,$width)));
        } finally { @unlink($path); }
    }
    public function test_oversized_choice_list_is_rejected_instead_of_truncated(): void {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new SubmissionDbf)->save('/unused.dbf',[['submitted_choices'=>str_repeat('001|',70)]]);
    }
    public function test_unicode_is_rejected_instead_of_corrupted(): void {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new SubmissionDbf)->save('/unused.dbf',[['name'=>'বাংলা']]);
    }
}
