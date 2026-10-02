<?php
namespace App\Services\Choice;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
class SubmissionDbf {
    private function encoded(string $value,string $field): string {
        $encoded=@iconv('UTF-8','Windows-1252',$value);
        if ($encoded===false) throw ValidationException::withMessages(['export'=>'Use XLSX for Unicode text that Windows-1252 DBF cannot represent ('.$field.').']);
        return $encoded;
    }
    public function save(string $path,array $rows): void {
        $widths=['SUBCHOICES'=>50,'UNSELECTED'=>50]; $records=[];
        foreach ($rows as $row) {
            $record=[];
            foreach (['USER'=>'user','REG'=>'reg','NAME'=>'name','FNAME'=>'fname','MNAME'=>'mname','SUBCHOICES'=>'submitted_choices','UNSELECTED'=>'unselected_choices'] as $field=>$key) {
                $record[$field]=$this->encoded((string)($row[$key]??''),$field);
                if (isset($widths[$field])) $widths[$field]=max($widths[$field],strlen($record[$field]));
            }
            $date=$row['b_date']??'';
            $record['B_DATE']=$date ? Carbon::parse($date)->format('d-m-Y') : '';
            $record['SUBMITTED']=!empty($row['submission_status']) ? 'TRUE':'FALSE';
            $records[]=$record;
        }
        // Both choice columns keep a useful minimum, expanding only as needed.
        foreach ($widths as $field=>$width) {
            if ($width>254) throw ValidationException::withMessages(['export'=>$field.' exceeds the 254-byte single-file DBF limit. Use XLSX; no data has been truncated.']);
            $widths[$field]=min(254,(int)(ceil($width/10)*10));
        }
        $fields=[];
        foreach (['USER'=>10,'REG'=>10,'NAME'=>50,'FNAME'=>50,'MNAME'=>50,'B_DATE'=>10,'SUBCHOICES'=>$widths['SUBCHOICES'],'UNSELECTED'=>$widths['UNSELECTED'],'SUBMITTED'=>5] as $field=>$width) $fields[]=['name'=>$field,'type'=>'C','length'=>$width];
        foreach ($records as $record) foreach ($fields as $field) {
            if (strlen($record[$field['name']])>$field['length']) throw ValidationException::withMessages(['export'=>$field['name'].' exceeds its '.$field['length'].'-character DBF field. Use XLSX to retain the complete value.']);
        }
        (new LegacyDbfWriter)->write($path,$fields,$records);
        $handle=fopen($path,'r+b'); if (!$handle) throw new \RuntimeException('Cannot set DBF code page.');
        try { fseek($handle,29); fwrite($handle,chr(0x03)); } finally { fclose($handle); }
    }
}
