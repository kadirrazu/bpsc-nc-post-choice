<?php
namespace App\Services\Choice;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\{Spreadsheet,Cell\DataType,Reader\Xlsx,Writer\Xlsx as XlsxWriter};
class ChoiceWorkbook {
    public const HEADERS=['choice_code','choice_title','post_count','sort_order'];
    private function installed(): void {
        if (!class_exists(Spreadsheet::class)) throw ValidationException::withMessages(['xlsx'=>'Install the XLSX dependency with: composer require phpoffice/phpspreadsheet']);
    }
    public function sample(): Spreadsheet {
        $this->installed(); $book=new Spreadsheet; $sheet=$book->getActiveSheet(); $sheet->setTitle('Choices');
        $sheet->fromArray(self::HEADERS,null,'A1');
        $sheet->setCellValueExplicit('A2','001',DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B2','Assistant Programmer - Ministry A',DataType::TYPE_STRING);
        $sheet->setCellValue('C2',2); $sheet->setCellValue('D2',1);
        $sheet->setCellValueExplicit('A3','002',DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B3','Assistant Programmer - Ministry B',DataType::TYPE_STRING);
        $sheet->setCellValue('C3',1); $sheet->setCellValue('D3',2);
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE6EEF6');
        $sheet->getColumnDimension('A')->setWidth(20); $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getColumnDimension('C')->setWidth(16); $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:D3');
        return $book;
    }
    public function read(string $path): array {
        $this->installed();
        $reader=new Xlsx; $reader->setReadDataOnly(false);
        try {
            $info=$reader->listWorksheetInfo($path);
            if (count($info)!==1 || $info[0]['totalRows']>ChoiceEditor::MAX_ROWS+1 || $info[0]['totalColumns']>4) throw ValidationException::withMessages(['xlsx'=>'Use one sheet, four columns and at most 500 choice rows.']);
            $book=$reader->load($path);
        } catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) { throw ValidationException::withMessages(['xlsx'=>'Unable to read this XLSX file. Use the downloaded sample.']); }
        try {
            $sheet=$book->getSheet(0);
            foreach (self::HEADERS as $i=>$header) {
                $cell=$sheet->getCell([$i+1,1]);
                if ($cell->getDataType()===DataType::TYPE_FORMULA || trim((string)$cell->getValue())!==$header) throw ValidationException::withMessages(['xlsx'=>'Headers must be: '.implode(', ',self::HEADERS)]);
            }
            $rows=[];
            for ($r=2;$r<=$sheet->getHighestDataRow();$r++) {
                $values=[];
                for ($c=1;$c<=4;$c++) {
                    $cell=$sheet->getCell([$c,$r]);
                    if ($cell->getDataType()===DataType::TYPE_FORMULA) throw ValidationException::withMessages(['xlsx'=>'Row '.$r.': formulas are not allowed. Use plain values.']);
                    $values[]=trim((string)$cell->getFormattedValue());
                }
                if (implode('',$values)==='') continue;
                $rows[]=['code'=>$values[0],'title'=>$values[1],'post_count'=>$values[2]==='' ? null : $values[2],'sort_order'=>$values[3]];
            }
            return (new ChoiceEditor)->rows($rows);
        } finally { $book->disconnectWorksheets(); }
    }
}
