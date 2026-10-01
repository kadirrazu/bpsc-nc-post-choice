<?php
namespace App\Services\Choice;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
class CandidateCsvReader {
    public const REQUIRED=['user','reg','name','b_date'];
    public const OPTIONAL=['fname','mname','dist_code','dist_name','unit','post_code','post_name','ministry','ssc_roll','ssc_year','hsc_roll','hsc_year','nid'];
    public function read(string $path): array {
        $handle=fopen($path,'rb');
        if (!$handle) throw new InvalidArgumentException('Unable to read file.');
        try {
            $header=fgetcsv($handle,0,',','"','');
            if (!$header) throw new InvalidArgumentException('The file is empty.');
            $records=(function () use ($handle) {
                $line=1;
                while (($values=fgetcsv($handle,0,',','"',''))!==false) yield ['line'=>++$line,'values'=>$values,'errors'=>[]];
            })();
            return $this->validateRecords($header,$records);
        } finally { fclose($handle); }
    }
    public function validateRecords(array $header,iterable $records): array {
        $header=array_map(fn($v)=>strtolower(trim((string)$v,"\xEF\xBB\xBF \t\n\r")),$header);
        if (count($header)!==count(array_unique($header))) throw new InvalidArgumentException('Duplicate column headers.');
        if (array_diff(self::REQUIRED,$header)) throw new InvalidArgumentException('Required columns: '.implode(', ',self::REQUIRED));
        $rows=[]; $errors=[]; $row_numbers=[]; $seenUser=[]; $seenReg=[]; $count=0;
        foreach ($records as $record) {
            $line=$record['line']; $values=array_map(fn($v)=>trim((string)$v),$record['values']);
            if (implode('',$values)==='' && empty($record['errors'])) continue;
            if (++$count>(int)config('choice.import_max_rows',10000)) throw new InvalidArgumentException('File exceeds the row limit. Split it into smaller files.');
            if (count($values)!==count($header)) { $errors[]="Row {$line}: column count does not match."; continue; }
            $data=array_intersect_key(array_combine($header,$values),array_flip(array_merge(self::REQUIRED,self::OPTIONAL)));
            $rules=['user'=>'required|string|max:10','reg'=>'required|string|max:10','name'=>'required|string|max:255'];
            foreach (self::OPTIONAL as $key) $rules[$key]='nullable|string|max:'.(in_array($key,['ssc_roll','ssc_year','hsc_roll','hsc_year','nid']) ? '30' : '255');
            foreach (['fname','mname'] as $key) $data[$key]=($data[$key] ?? '')!=='' ? $data[$key] : null;
            $rowErrors=array_merge($record['errors'] ?? [],Validator::make($data,$rules)->errors()->all());
            try { $data['b_date']=(new BirthDateNormalizer)->normalize($data['b_date']); }
            catch (InvalidArgumentException $e) { $rowErrors[]=$e->getMessage(); }
            $user=mb_strtolower($data['user']); $reg=mb_strtolower($data['reg']);
            if (isset($seenUser[$user]) || isset($seenReg[$reg])) $rowErrors[]='Duplicate user or reg in this file.';
            $seenUser[$user]=true; $seenReg[$reg]=true;
            foreach ($rowErrors as $error) $errors[]="Row {$line}: {$error}";
            $rows[]=$data; $row_numbers[]=$line;
        }
        if (!$rows && !$errors) throw new InvalidArgumentException('No candidate rows found.');
        return compact('rows','errors','row_numbers');
    }
}
