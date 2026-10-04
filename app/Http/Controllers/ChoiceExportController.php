<?php
namespace App\Http\Controllers;
use App\Models\ChoiceEvent;
use App\Services\Choice\{ChoiceExportData,ChoiceWorkbook,ExportFilename,SubmissionDbf,SubmissionReceiptPdf};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ChoiceExportController extends Controller {
    private function temporary(string $suffix): string {
        $dir=storage_path('app/choice-exports');
        if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new \RuntimeException('Cannot create export directory.');
        return $dir.'/'.bin2hex(random_bytes(16)).$suffix;
    }
    public function record(Request $r,ChoiceEvent $choiceEvent,string $format,ChoiceExportData $data,ChoiceWorkbook $workbook,SubmissionReceiptPdf $pdf) {
        abort_unless(in_array($format,['xlsx','pdf'],true),404);
        $summary=$data->summary($choiceEvent); $rows=$data->choices($choiceEvent); $stamp=now('Asia/Dhaka')->format('d M Y, h:i:s A').' (UTC+06:00)';
        $filename=ExportFilename::make('event-record',$choiceEvent->post_code,$format);
        if ($format==='pdf') {
            $html=view('choice.events.record-pdf',['event'=>$choiceEvent,'rows'=>$rows,'summary'=>$summary])->render();
            return response($pdf->render($html,$stamp,"Administrator's Signature"),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$filename.'"','Cache-Control'=>'private, no-store']);
        }
        $path=$this->temporary('.xlsx');
        try { $workbook->saveEvent($path,['order','code','title','post_count','post_code','post_title','organization','ministry'],$rows,['Commission'=>'Bangladesh Public Service Commission (BPSC)','Report'=>'Choice Event Administrative Record','Event title'=>$choiceEvent->title,'Post code'=>$choiceEvent->post_code,'Unit'=>$choiceEvent->unit,'Status'=>$choiceEvent->status,'Total candidates'=>$summary['total_candidates'],'Submitted candidates'=>$summary['submitted_candidates'],'Start'=>$choiceEvent->start_at->format('Y-m-d H:i:s'),'End'=>$choiceEvent->end_at->format('Y-m-d H:i:s'),'Instructions'=>$choiceEvent->instructions,'Generated'=>$stamp]); }
        catch (\Throwable $e) { @unlink($path); throw $e; }
        return response()->download($path,$filename,['Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
    }
    public function candidates(Request $r,ChoiceEvent $choiceEvent,string $format,ChoiceExportData $data,ChoiceWorkbook $workbook,SubmissionDbf $dbf) {
        abort_unless(in_array($format,['xlsx','dbf'],true),404);
        $r->validate(['scope'=>['nullable',Rule::in(['all','submitted'])]]);
        $rows=$data->candidates($choiceEvent,$r->input('scope','submitted')==='submitted');
        $filename=ExportFilename::make('candidate-choices',$choiceEvent->post_code,$format);
        if ($format==='xlsx') {
            $path=$this->temporary('.xlsx');
            try { $workbook->save($path,['user','reg','name','fname','mname','b_date','submitted_choices','unselected_choices','submission_status'],$rows); }
            catch (\Throwable $e) { @unlink($path); throw $e; }
            return response()->download($path,$filename,['Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
        }
        $path=$this->temporary('.dbf');
        try { $dbf->save($path,$rows); }
        catch (\Throwable $e) { @unlink($path); throw $e; }
        return response()->download($path,$filename,['Content-Type'=>'application/x-dbf','Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
    }
}
