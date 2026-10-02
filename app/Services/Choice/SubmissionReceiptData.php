<?php
namespace App\Services\Choice;
use App\Models\{ChoiceEvent,EventCandidate};
use Illuminate\Support\{Carbon,Facades\DB};
class SubmissionReceiptData {
    public function build(ChoiceEvent $event,EventCandidate $person,$submission,?int $applicationId=null): array {
        $query=$person->applications()->whereHas('post',fn($q)=>$q->where('choice_event_id',$event->id));
        $application=$applicationId ? (clone $query)->whereKey($applicationId)->first() : null;
        $application ??= $query->orderBy('id')->first();
        $details=$submission->candidate_snapshot ? json_decode($submission->candidate_snapshot,true) : null;
        $details ??= ['user'=>$application?->user,'reg'=>$application?->reg,'name'=>$person->name,'fname'=>$person->fname,'mname'=>$person->mname,'b_date'=>$person->b_date->format('Y-m-d')];
        $details['dob']=Carbon::parse($details['b_date'])->format('d-m-Y');
        $details['token']=$submission->token;
        $details['submitted_at']=Carbon::parse($submission->submitted_at)->format('d M Y, h:i:s A').' (UTC+06:00)';
        $details['submitted_from']=$submission->submitted_ip;
        $items=DB::table('choice_submission_items')->join('choice_options','choice_options.id','=','choice_submission_items.choice_option_id')->where('choice_submission_id',$submission->id)->orderBy('preference_order')->get(['preference_order','code','title']);
        $printTimestamp=now('Asia/Dhaka')->format('d M Y, h:i:s A').' (UTC+06:00)';
        return compact('event','person','submission','items','details','printTimestamp');
    }
}
