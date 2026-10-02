<?php
namespace App\Services\Choice;
use Illuminate\Validation\ValidationException;
use Mpdf\{Mpdf,HTMLParserMode};
class BanglaReceiptPdf {
    /** OpenType shaping is required for Bengali conjuncts and vowel positioning. */
    public function render(string $html,string $printTimestamp,string $signatureLabel): string {
        if (!class_exists(Mpdf::class)) throw ValidationException::withMessages(['pdf'=>'Bangla PDF support requires: composer require mpdf/mpdf:^8.2']);
        $nikosh=public_path('fonts/Nikosh.ttf');
        $times=storage_path('app/receipt-fonts');
        if (!is_readable($times.'/times.ttf')) $times=rtrim((string)(getenv('WINDIR') ?: 'C:/Windows'),'/\\').'/Fonts';
        foreach ([$nikosh,$times.'/times.ttf',$times.'/timesbd.ttf'] as $font) {
            if (!is_readable($font)) throw ValidationException::withMessages(['pdf'=>'PDF font missing: '.$font]);
        }
        $temp=storage_path('app/mpdf-cache');
        if (!is_dir($temp) && !mkdir($temp,0755,true) && !is_dir($temp)) throw new \RuntimeException('Cannot create mPDF cache directory.');
        preg_match('/@page\s*\{[^}]*margin:\s*([\d.]+)pt/s',$html,$margin);
        $top=((float)($margin[1]??134))*25.4/72;
        $pdf=new Mpdf([
            'mode'=>'utf-8','format'=>'A4','orientation'=>'P','tempDir'=>$temp,
            'fontDir'=>[dirname($nikosh),$times],
            'fontdata'=>[
                'timesnewroman'=>['R'=>'times.ttf','B'=>'timesbd.ttf'],
                'nikosh'=>['R'=>'Nikosh.ttf','useOTL'=>0xFF],
            ],
            'default_font'=>'timesnewroman','default_font_size'=>12,
            'margin_left'=>12.7,'margin_right'=>12.7,'margin_top'=>$top,
            'margin_bottom'=>38.1,'margin_header'=>12.7,'margin_footer'=>12.7,
        ]);
        $pdf->SetTitle('BPSC Choice Record');
        $pdf->SetAuthor('Bangladesh Public Service Commission (BPSC)');
        preg_match('/<style[^>]*>(.*?)<\/style>/si',$html,$styles);
        $css=preg_replace('/@page\s*\{.*?\}/s','',$styles[1]??'');
        $css=str_replace('"Times New Roman"','timesnewroman',$css);
        preg_match('/<header[^>]*>(.*?)<\/header>/si',$html,$headerMatch);
        $header=$headerMatch[1]??'';
        if (preg_match('/<img[^>]*class="receipt-header-qr"[^>]*>/si',$header,$image)) {
            preg_match('/<div class="receipt-header-text">(.*?)<\/div>/si',$header,$text);
            $qr=preg_replace('/class="receipt-header-qr"/','style="width:27.5mm;height:27.5mm"',$image[0]);
            $header='<table class="pdf-page-header"><tr><td style="text-align:center">'.($text[1]??'').'</td><td style="width:29mm;text-align:right">'.$qr.'</td></tr></table><hr>';
        }
        $css.=' .pdf-header p{font-size:12pt;line-height:1.15;margin:2pt 0;} .pdf-header hr{border:0;border-top:0.6pt solid #777;margin:7pt 0 0;} .pdf-page-header{margin:0;width:100%;} .pdf-page-header td{border:0;padding:0;vertical-align:top;} .pdf-page-header p{margin:2pt 0;font-size:12pt;line-height:1.15;} .pdf-page-header h1{font-size:14pt;} .pdf-footer td{border:0;padding:0;}';
        $header='<div class="pdf-header" style="text-align:center;font-family:timesnewroman">'.$header.'</div>';
        preg_match('/<body[^>]*>(.*?)<\/body>/si',$html,$body);
        $body=preg_replace('/<header[^>]*>.*?<\/header>/si','',$body[1]??'');
        $body=str_replace(['width:15%','width:17%'],['width:18%','width:20%'],$body);
        $footer='<table class="pdf-footer" style="width:100%;font-family:timesnewroman;font-size:10pt"><tr><td></td><td style="width:55mm;text-align:center;padding-bottom:8mm"><div style="border-top:0.5pt solid #000;font-size:11pt">'.htmlspecialchars($signatureLabel,ENT_QUOTES,'UTF-8').'</div></td></tr><tr style="color:#404040"><td>Print Timestamp: '.htmlspecialchars($printTimestamp,ENT_QUOTES,'UTF-8').'</td><td style="text-align:right">Page {PAGENO} of {nbpg}</td></tr></table>';
        $pdf->WriteHTML($css,HTMLParserMode::HEADER_CSS);
        $pdf->SetHTMLHeader($this->fontRuns($header));
        $pdf->SetHTMLFooter($footer);
        $pdf->WriteHTML($this->fontRuns($body),HTMLParserMode::HTML_BODY);
        // Fixed-position credit is added to each generated page, inside the left margin.
        $lastPage=$pdf->page;
        for ($page=1;$page<=$lastPage;$page++) {
            $pdf->page=$page;
            $credit='Software Developed By: <b>IT Section, BPSC</b>';
            $pdf->WriteFixedPosHTML($credit,5,195,65,4,'visible',['DIV',['STYLE'=>'position:absolute;left:5mm;top:195mm;width:65mm;height:4mm;rotate:-90;font-family:timesnewroman;font-size:8pt;color:#595959;white-space:nowrap'],0,0]);
        }
        $pdf->page=$lastPage;
        return $pdf->Output('','S');
    }
    /** Wrap text nodes only; retain escaping and markup, with English in Times New Roman. */
    public function fontRuns(string $html): string {
        $document=new \DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET);
            $xpath=new \DOMXPath($document);
            $nodes=[];
            foreach ($xpath->query('//*[@data-choice-title]//text()[not(ancestor::script) and not(ancestor::style)]') as $node) $nodes[]=$node;
            foreach ($nodes as $node) {
                $pieces=preg_split('/([\x{0980}-\x{09FF}\x{200C}\x{200D}]+(?:[ \t]+[\x{0980}-\x{09FF}\x{200C}\x{200D}]+)*)/u',$node->nodeValue,-1,PREG_SPLIT_DELIM_CAPTURE);
                if (count($pieces)<2) continue;
                $fragment=$document->createDocumentFragment();
                foreach ($pieces as $index=>$piece) {
                    if ($index%2===1) {
                        $span=$document->createElement('span'); $span->setAttribute('style','font-family:nikosh');
                        $span->appendChild($document->createTextNode($piece)); $fragment->appendChild($span);
                    } else $fragment->appendChild($document->createTextNode($piece));
                }
                $node->parentNode->replaceChild($fragment,$node);
            }
            $result=''; foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $child) $result.=$document->saveHTML($child);
            return $result;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
}
