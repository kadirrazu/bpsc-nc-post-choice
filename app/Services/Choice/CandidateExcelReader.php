<?php
namespace App\Services\Choice;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\{Spreadsheet,Cell\DataType,Cell\Coordinate,Reader\Xls,Reader\Xlsx};
class CandidateExcelReader {
    private function installed(): void {
        if (!class_exists(Spreadsheet::class)) throw new InvalidArgumentException('Excel support requires: composer require phpoffice/phpspreadsheet');
    }
    public function read(string $path,string $extension): array {
        $this->installed();
        if (!in_array($extension,['xls','xlsx'],true)) throw new InvalidArgumentException('Use an XLS or XLSX workbook.');
        $reader=$extension==='xls' ? new Xls : new Xlsx;
        $reader->setReadDataOnly(false);
        try {
            $info=$reader->listWorksheetInfo($path);
            if (!$info) throw new InvalidArgumentException('The workbook contains no worksheets.');
            $first=$info[0];
            if ($first['totalRows']>(int)config('choice.import_max_rows',10000)+1 || $first['totalColumns']>100) throw new InvalidArgumentException('Use at most 10,000 candidate rows and 100 columns on the first worksheet.');
            $reader->setLoadSheetsOnly([$first['worksheetName']]);
            $book=$reader->load($path);
        } catch (InvalidArgumentException $e) { throw $e; }
        catch (\Throwable $e) { throw new InvalidArgumentException('Unable to read this Excel file. Use the downloaded sample.',0,$e); }
        try {
            $sheet=$book->getSheet(0); $width=$first['totalColumns']; $header=[];
            for ($c=1;$c<=$width;$c++) {
                $cell=$sheet->getCell([$c,1]);
                if ($cell->getDataType()===DataType::TYPE_FORMULA) throw new InvalidArgumentException('Column headers must be plain text.');
                $header[]=trim((string)$cell->getValue());
            }
            // Ignore unused trailing header columns, retaining unknown named columns.
            while ($header && end($header)==='') array_pop($header);
            $width=count($header);
            $records=(function () use ($sheet,$width) {
                for ($r=2;$r<=$sheet->getHighestDataRow();$r++) {
                    $values=[]; $errors=[];
                    for ($c=1;$c<=$width;$c++) {
                        $cell=$sheet->getCell([$c,$r]);
                        if ($cell->getDataType()===DataType::TYPE_FORMULA) { $errors[]='Formulas are not allowed; use plain values.'; $values[]=''; }
                        else $values[]=trim((string)$cell->getFormattedValue());
                    }
                    yield ['line'=>$r,'values'=>$values,'errors'=>array_unique($errors)];
                }
            })();
            return (new CandidateCsvReader)->validateRecords($header,$records);
        } finally { $book->disconnectWorksheets(); }
    }
    public function sample(): Spreadsheet {
        $this->installed(); $book=new Spreadsheet; $sheet=$book->getActiveSheet(); $sheet->setTitle('Candidates');
        $headers=array_merge(['user','reg','name','fname','mname','b_date'],array_values(array_diff(CandidateCsvReader::OPTIONAL,['fname','mname'])));
        foreach ($headers as $i=>$header) {
            $sheet->setCellValueExplicit([$i+1,1],$header,DataType::TYPE_STRING);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i+1))->setWidth(in_array($header,['name','fname','mname','post_name','ministry']) ? 30 : 18);
        }
        $sample=['user'=>'U000000001','reg'=>'0012345678','name'=>'SAMPLE CANDIDATE','fname'=>'SAMPLE FATHER','mname'=>'SAMPLE MOTHER','b_date'=>'10101997','dist_code'=>'01','dist_name'=>'Dhaka','unit'=>'Unit 01','post_code'=>'260030','post_name'=>'Assistant Programmer','ministry'=>'Sample Ministry','ssc_roll'=>'001234','ssc_year'=>'2013','hsc_roll'=>'005678','hsc_year'=>'2015','nid'=>'0012345678901'];
        foreach ($headers as $i=>$header) $sheet->setCellValueExplicit([$i+1,2],$sample[$header],DataType::TYPE_STRING);
        $sheet->getStyle('A:Q')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
        $sheet->getStyle('A1:Q1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE6EEF6');
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:Q2');
        return $book;
    }
}
