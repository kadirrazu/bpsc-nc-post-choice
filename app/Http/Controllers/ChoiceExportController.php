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
        $summary=$data->summary($choiceEvent); $postSummary=$choiceEvent->multiple_posts ? $data->postSummary($choiceEvent) : []; $rows=$data->choices($choiceEvent); $stamp=now('Asia/Dhaka')->format('d M Y, h:i:s A').' (UTC+06:00)';
        $filename=ExportFilename::make('event-record',$choiceEvent->post_code,$format);
        if ($format==='pdf') {
            $html=view('choice.events.record-pdf',['event'=>$choiceEvent,'rows'=>$rows,'summary'=>$summary,'postSummary'=>$postSummary])->render();
            return response($pdf->render($html,$stamp,"Administrator's Signature"),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$filename.'"','Cache-Control'=>'private, no-store']);
        }
        $path=$this->temporary('.xlsx');
        try { $workbook->saveEvent($path,['order','code','title','post_count','post_code','post_title','organization','ministry'],$rows,['Commission'=>'Bangladesh Public Service Commission (BPSC)','Report'=>'Choice Event Administrative Record','Event title'=>$choiceEvent->title,'Post code'=>$choiceEvent->post_code,'Unit'=>$choiceEvent->unit,'Status'=>$choiceEvent->status,'Total candidates'=>$summary['total_candidates'],'Submitted candidates'=>$summary['submitted_candidates'],'Start'=>$choiceEvent->start_at->format('Y-m-d H:i:s'),'End'=>$choiceEvent->end_at->format('Y-m-d H:i:s'),'Instructions'=>$choiceEvent->instructions,'Generated'=>$stamp]+($choiceEvent->multiple_posts ? ['Unique submissions'=>$summary['submitted_candidates'],'Total applications'=>array_sum(array_column($postSummary,'total_candidates'))] : []),$postSummary); }
        catch (\Throwable $e) { @unlink($path); throw $e; }
        return response()->download($path,$filename,['Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
    }
    public function candidates(Request $r,ChoiceEvent $choiceEvent,string $format,ChoiceExportData $data,ChoiceWorkbook $workbook,SubmissionDbf $dbf) {
        abort_unless(in_array($format,['xlsx','dbf'],true),404);
        $r->validate(['scope'=>['nullable',Rule::in(['all','submitted'])]]);
        $postId=null;
        if ($choiceEvent->multiple_posts && $r->filled('post_id')) {
            $r->validate(['post_id'=>'integer|min:1']);
            $selected=$choiceEvent->posts()->findOrFail($r->integer('post_id')); $postId=$selected->id;
        }
        $rows=$data->candidates($choiceEvent,$r->input('scope','submitted')==='submitted',$postId);
        $filename=ExportFilename::make('candidate-choices',$selected->post_code??$choiceEvent->post_code,$format);
        if ($format==='xlsx') {
            $path=$this->temporary('.xlsx');
            try { $columns=['user','reg','name','fname','mname','b_date','submitted_choices','unselected_choices','submission_status']; if ($choiceEvent->multiple_posts) $columns[]='applied_post_code'; $workbook->save($path,$columns,$rows); }
            catch (\Throwable $e) { @unlink($path); throw $e; }
            return response()->download($path,$filename,['Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
        }
        $path=$this->temporary('.dbf');
        try { $dbf->save($path,$rows,$choiceEvent->multiple_posts); }
        catch (\Throwable $e) { @unlink($path); throw $e; }
        return response()->download($path,$filename,['Content-Type'=>'application/x-dbf','Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
    }
    public function matched(ChoiceEvent $choiceEvent,\App\Models\EventPost $post,string $format,ChoiceExportData $data,\App\Services\Choice\MultiplePostExport $export,ChoiceWorkbook $workbook,SubmissionDbf $dbf) {
        abort_unless($choiceEvent->multiple_posts && $post->choice_event_id===$choiceEvent->id && in_array($format,['xlsx','dbf'],true),404);
        $dataset=$export->dataset($choiceEvent,$post,$data);
        $path=$this->temporary('.'.$format); $filename=ExportFilename::make('matched-candidates',$post->post_code,$format);
        try {
            if ($format==='xlsx') $workbook->save($path,$dataset['columns'],$dataset['rows']);
            else $dbf->save($path,$dataset['rows'],true,$dataset['mapping']);
        } catch (\Throwable $e) { @unlink($path); throw $e; }
        return response()->download($path,$filename,['Cache-Control'=>'private, no-store','Content-Type'=>$format==='dbf' ? 'application/x-dbf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
