<?php
namespace App\Services\Choice;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\{Spreadsheet,Cell\DataType,Writer\Xlsx};
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
        $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx; $reader->setReadDataOnly(false);
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

    public function saveEvent(string $path,array $columns,array $rows,array $metadata): void {
        $book=new Spreadsheet; $eventSheet=$book->getActiveSheet(); $eventSheet->setTitle('Event');
        $row=1;
        foreach ($metadata as $label=>$value) {
            $eventSheet->setCellValueExplicit([1,$row],$label,DataType::TYPE_STRING);
            $eventSheet->setCellValueExplicit([2,$row],(string)$value,DataType::TYPE_STRING); $row++;
        }
        $eventSheet->getColumnDimension('A')->setWidth(24); $eventSheet->getColumnDimension('B')->setWidth(85);
        $eventSheet->getStyle('A1:A'.($row-1))->getFont()->setBold(true);
        $eventSheet->freezePane('B1');
        $choiceSheet=$book->createSheet(); $choiceSheet->setTitle('Choices');
        foreach ($columns as $i=>$name) $choiceSheet->setCellValueExplicit([$i+1,1],$name,DataType::TYPE_STRING);
        $row=1;
        foreach ($rows as $record) { $row++; foreach ($columns as $i=>$name) {
            $value=$record[$name]??'';
            $choiceSheet->setCellValueExplicit([$i+1,$row],$value,is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        } }
        $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $choiceSheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);
        $choiceSheet->freezePane('A2'); $choiceSheet->setAutoFilter('A1:'.$last.$row);
        foreach ($columns as $i=>$name) $choiceSheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1))->setWidth(in_array($name,['title','post_title','organization','ministry']) ? 45 : 18);
        foreach ([$eventSheet,$choiceSheet] as $sheet) {
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('center');
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getFont()->setName('Times New Roman')->setSize(11);
            $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1,1);
            $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
            $sheet->getHeaderFooter()->setOddHeader('&CBangladesh Public Service Commission (BPSC)');
            $sheet->getHeaderFooter()->setOddFooter('&LGenerated: '.now()->format('d M Y H:i:s').' &RPage &P of &N');
        }
        $book->setActiveSheetIndex(0); (new Xlsx($book))->save($path); $book->disconnectWorksheets();
    }
    public function save(string $path,array $columns,array $rows,array $metadata=[]): void {
        $book=new Spreadsheet; $sheet=$book->getActiveSheet(); $sheet->setTitle('Export'); $n=count($columns); $row=1;
        foreach ($metadata as $label=>$value) {
            $sheet->setCellValueExplicit([1,$row],$label,DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2,$row],(string)$value,DataType::TYPE_STRING); $row++;
        }
        if ($metadata) $row++;
        $header=$row;
        foreach ($columns as $i=>$name) $sheet->setCellValueExplicit([$i+1,$row],$name,DataType::TYPE_STRING);
        foreach ($rows as $record) { $row++; foreach ($columns as $i=>$name) {
            $value=$record[$name]??''; $type=is_bool($value) ? DataType::TYPE_BOOL : (is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$i+1,$row],$value,$type);
        } }
        $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
        $sheet->getStyle('A'.$header.':'.$last.$header)->getFont()->setBold(true);
        $sheet->freezePane('A'.($header+1)); $sheet->setAutoFilter('A'.$header.':'.$last.max($header,$row));
        foreach (range(1,$n) as $col) $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col))->setWidth(in_array($columns[$col-1],['title','name','fname','mname','post_title','organization','ministry','submitted_choices','unselected_choices']) ? 40 : 20);
        $sheet->getStyle('A1:'.$last.$row)->getAlignment()->setVertical('center')->setWrapText(true);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,$header);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
        $sheet->getStyle('A1:'.$last.$row)->getFont()->setName('Times New Roman')->setSize(11);
        $sheet->getHeaderFooter()->setOddHeader('&CBangladesh Public Service Commission (BPSC)');
        $sheet->getHeaderFooter()->setOddFooter('&LGenerated: '.now()->format('d M Y H:i:s').' &RPage &P of &N');
        (new Xlsx($book))->save($path); $book->disconnectWorksheets();
    }
}
