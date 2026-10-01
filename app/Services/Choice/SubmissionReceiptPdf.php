<?php
namespace App\Services\Choice;
use Dompdf\{Dompdf,Options};
use Illuminate\Validation\ValidationException;
class SubmissionReceiptPdf {
    public function render(string $html,string $printTimestamp): string {
        if (!class_exists(Dompdf::class)) throw ValidationException::withMessages(['pdf'=>'PDF support requires: composer require dompdf/dompdf']);
        $options=new Options;
        $options->set('isRemoteEnabled',false); $options->set('isPhpEnabled',false);
        $fontDirectory=storage_path('app/receipt-fonts');
        $windowsFonts=rtrim((string)(getenv('WINDIR') ?: 'C:/Windows'),'/\\').'/Fonts';
        $source=is_readable($fontDirectory.'/times.ttf') ? $fontDirectory : $windowsFonts;
        foreach (['times.ttf','timesbd.ttf'] as $file) {
            if (!is_readable($source.'/'.$file)) throw ValidationException::withMessages(['pdf'=>'Times New Roman font files are missing. Copy times.ttf and timesbd.ttf into storage/app/receipt-fonts.']);
        }
        $cache=storage_path('app/dompdf-font-cache');
        if (!is_dir($cache) && !mkdir($cache,0755,true) && !is_dir($cache)) throw new \RuntimeException('Cannot create PDF font cache.');
        $options->set('chroot',[base_path(),realpath($source)]);
        if (!is_writable($cache)) throw new \RuntimeException('PDF font cache must be writable: '.$cache);
        $options->set('fontDir',$cache); $options->set('fontCache',$cache); $options->set('tempDir',$cache);
        $options->set('defaultFont','Times New Roman');
        $pdf=new Dompdf($options);
        foreach (['normal'=>'times.ttf','bold'=>'timesbd.ttf'] as $weight=>$file) {
            // Read trusted local font bytes in PHP: avoid Windows file URI/chroot parsing.
            $bytes=file_get_contents($source.'/'.$file);
            if ($bytes===false || $bytes==='') throw new \RuntimeException('Cannot read PDF font: '.$file);
            $uri='data:font/ttf;base64,'.base64_encode($bytes);
            if (!$pdf->getFontMetrics()->registerFont(['family'=>'Times New Roman','style'=>'normal','weight'=>$weight],$uri)) throw new \RuntimeException('Cannot register PDF font '.$file.'. Check that the font file is valid and storage/app/dompdf-font-cache is writable.');
        }
        $pdf->setPaper('A4','portrait'); $pdf->loadHtml($html,'UTF-8'); $pdf->render();
        $pdf->getCanvas()->page_script(function ($pageNumber,$pageCount,$canvas,$fontMetrics) use ($printTimestamp) {
            $font=$fontMetrics->getFont('Times New Roman','normal'); $size=10; $color=[0.25,0.25,0.25];
            // 75% black on white; half-inch outer boundary on every page.
            $margin=36; $y=$canvas->get_height()-$margin-11;
            $right=$canvas->get_width()-$margin;
            $signatureY=$y-34;
            $canvas->line($right-150,$signatureY,$right,$signatureY,[0,0,0],0.5);
            $label="Candidate's Signature";
            $labelWidth=$fontMetrics->getTextWidth($label,$font,11);
            $canvas->text($right-75-$labelWidth/2,$signatureY+4,$label,$font,11,[0,0,0]);
            $canvas->text($margin,$y,'Print Timestamp: '.$printTimestamp,$font,$size,$color);
            $text='Page '.$pageNumber.' of '.$pageCount;
            $width=$fontMetrics->getTextWidth($text,$font,$size);
            $canvas->text($canvas->get_width()-$margin-$width,$y,$text,$font,$size,$color);
        });
        return $pdf->output();
    }
}
