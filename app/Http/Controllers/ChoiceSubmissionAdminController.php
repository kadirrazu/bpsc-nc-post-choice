<?php
namespace App\Http\Controllers;
use App\Enums\UserRole;
use App\Services\Choice\{SubmissionReceiptData,SubmissionReceiptPdf,ReceiptQrCode,ExportFilename};
use App\Models\{ChoiceEvent,EventCandidate};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};
class ChoiceSubmissionAdminController extends Controller {
    public function index(Request $r,ChoiceEvent $choiceEvent) {
        $data=$r->validate(['q'=>'nullable|string|max:100','per_page'=>['nullable',Rule::in([25,50,100])]]);
        $q=DB::table('candidate_applications as a')->join('event_candidates as c','c.id','=','a.event_candidate_id')->join('event_posts as p','p.id','=','a.event_post_id')->join('choice_submissions as s',fn($j)=>$j->on('s.event_candidate_id','=','c.id')->where('s.status','SUBMITTED'))->where('c.choice_event_id',$choiceEvent->id)->where('p.choice_event_id',$choiceEvent->id);
        if ($search=$data['q']??null) $q->where(fn($q)=>$q->where('a.user','like','%'.$search.'%')->orWhere('a.reg','like','%'.$search.'%')->orWhere('c.name','like','%'.$search.'%'));
        $rows=$q->orderBy('c.id')->orderBy('a.id')->select('c.id','c.name','a.user','a.reg','p.post_code','s.id as submission_id','s.submitted_choices','s.submitted_at','s.token')->paginate((int)($data['per_page']??25))->withQueryString();
        $counts=['candidates'=>$choiceEvent->candidates()->count(),'submitted'=>DB::table('choice_submissions')->whereIn('event_candidate_id',$choiceEvent->candidates()->select('id'))->where('status','SUBMITTED')->count(),'cancelled'=>DB::table('choice_submissions')->whereIn('event_candidate_id',$choiceEvent->candidates()->select('id'))->where('status','CANCELLED')->count()];
        $history=DB::table('choice_submissions as s')->join('event_candidates as c','c.id','=','s.event_candidate_id')->leftJoin('users as u','u.id','=','s.cancelled_by')->where('c.choice_event_id',$choiceEvent->id)->where('s.status','CANCELLED')->orderByDesc('s.cancelled_at')->limit(20)->get(['s.id','s.token','c.name','s.cancelled_at','s.cancellation_reason','u.name as administrator']);
        return view('choice.submissions.index',['event'=>$choiceEvent,'rows'=>$rows,'counts'=>$counts,'history'=>$history]);
    }
    public function receipt(Request $r,ChoiceEvent $choiceEvent,int $submission,SubmissionReceiptData $dataBuilder,SubmissionReceiptPdf $pdf,ReceiptQrCode $qr) {
        $record=DB::table('choice_submissions')->where('id',$submission)->where('status','SUBMITTED')->whereIn('event_candidate_id',$choiceEvent->candidates()->select('id'))->first();
        abort_unless($record,404,'No active submission found for this event.');
        $person=$choiceEvent->candidates()->findOrFail($record->event_candidate_id);
        $data=$dataBuilder->build($choiceEvent,$person,$record);
        $data['qrDataUri']=$qr->dataUri($data['details']);
        $bytes=$pdf->render(view('choice.candidate.receipt-pdf',$data)->render(),$data['printTimestamp']);
        $filename=ExportFilename::make('choice-receipt',$choiceEvent->post_code,'pdf');
        return response($bytes,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$filename.'"','Cache-Control'=>'private, no-store']);
    }
    private function admin(Request $r): void { abort_unless($r->user()->role===UserRole::Admin,403); }
    private function audit(Request $r,ChoiceEvent $event,string $action,array $details): void {
        DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$r->user()->id,'action'=>$action,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);
    }
    public function cancel(Request $r,ChoiceEvent $choiceEvent,int $submission) {
        $this->admin($r); $data=$r->validate(['reason'=>'required|string|max:2000']);
        DB::transaction(function () use ($r,$choiceEvent,$submission,$data) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $s=DB::table('choice_submissions')->where('id',$submission)->whereIn('event_candidate_id',$event->candidates()->select('id'))->lockForUpdate()->first(); abort_unless($s,404);
            EventCandidate::whereKey($s->event_candidate_id)->lockForUpdate()->firstOrFail();
            if ($s->status!=='SUBMITTED') throw ValidationException::withMessages(['reason'=>'This submission is already cancelled.']);
            DB::table('choice_submissions')->where('id',$s->id)->update(['status'=>'CANCELLED','active_slot'=>null,'cancelled_by'=>$r->user()->id,'cancelled_at'=>now(),'cancellation_reason'=>$data['reason'],'updated_at'=>now()]);
            $this->audit($r,$event,'SUBMISSION_CANCELLED',['submission_id'=>$s->id,'candidate_id'=>$s->event_candidate_id,'token'=>$s->token,'reason'=>$data['reason']]);
        });
        return back()->with('success','Submission cancelled. Candidate may sign in and submit again while this event is accepting choices.');
    }
    private function scopedSubmission(ChoiceEvent $event,int $id) {
        $row=DB::table('choice_submissions')->where('id',$id)->whereIn('event_candidate_id',$event->candidates()->select('id'))->first(); abort_unless($row,404); return $row;
    }
    public function confirmDelete(Request $r,ChoiceEvent $choiceEvent,int $submission) {
        $this->admin($r); $record=$this->scopedSubmission($choiceEvent,$submission);
        $person=EventCandidate::findOrFail($record->event_candidate_id);
        return view('choice.submissions.confirm',['event'=>$choiceEvent,'record'=>$record,'person'=>$person,'count'=>1]);
    }
    public function confirmClear(Request $r,ChoiceEvent $choiceEvent) {
        $this->admin($r); $count=DB::table('choice_submissions')->whereIn('event_candidate_id',$choiceEvent->candidates()->select('id'))->count();
        return view('choice.submissions.confirm',['event'=>$choiceEvent,'record'=>null,'person'=>null,'count'=>$count]);
    }
    public function destroy(Request $r,ChoiceEvent $choiceEvent,int $submission) {
        $this->admin($r); $r->validate(['confirmation'=>['required',Rule::in(['DELETE'])]]);
        DB::transaction(function () use ($r,$choiceEvent,$submission) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail(); $row=$this->scopedSubmission($event,$submission);
            EventCandidate::whereKey($row->event_candidate_id)->lockForUpdate()->firstOrFail();
            DB::table('choice_submission_items')->where('choice_submission_id',$row->id)->delete();
            DB::table('choice_submissions')->where('id',$row->id)->delete();
            $this->audit($r,$event,'SUBMISSION_DELETED',['submission_id'=>$row->id,'candidate_id'=>$row->event_candidate_id,'token'=>$row->token]);
        });
        return redirect()->route('choice-submissions.index',$choiceEvent)->with('success','Submission deleted. Candidate may submit again while the event is accepting choices.');
    }
    public function clear(Request $r,ChoiceEvent $choiceEvent) {
        $this->admin($r); $r->validate(['confirmation'=>['required',Rule::in(['CLEAR ALL'])]]);
        $count=DB::transaction(function () use ($r,$choiceEvent) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $query=DB::table('choice_submissions')->whereIn('event_candidate_id',$event->candidates()->select('id')); $count=(clone $query)->count();
            DB::table('choice_submission_items')->whereIn('choice_submission_id',(clone $query)->select('id'))->delete();
            $query->delete();
            $this->audit($r,$event,'ALL_SUBMISSIONS_CLEARED',['count'=>$count]); return $count;
        });
        return redirect()->route('choice-submissions.index',$choiceEvent)->with('success',$count.' submissions cleared. Candidates and choices remain available.');
    }
}
