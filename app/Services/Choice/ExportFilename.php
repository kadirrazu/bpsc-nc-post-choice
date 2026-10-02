<?php
namespace App\Services\Choice;
class ExportFilename {
    public static function make(string $label,?string $postCode,string $extension): string {
        $code=preg_replace('/[^A-Za-z0-9_-]+/','-',trim((string)$postCode));
        $code=trim($code,'-') ?: 'general';
        return $label.'-'.$code.'-'.now('Asia/Dhaka')->format('Ymd_His').'.'.$extension;
    }
}
