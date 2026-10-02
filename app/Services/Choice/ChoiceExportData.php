<?php
namespace App\Services\Choice;
use App\Models\ChoiceEvent;
use Illuminate\Support\Facades\DB;
class ChoiceExportData {
    public function candidates(ChoiceEvent $event,bool $submittedOnly=false): array {
        $q=DB::table('candidate_applications as a')->join('event_candidates as c','c.id','=','a.event_candidate_id')->join('event_posts as p','p.id','=','a.event_post_id')->leftJoin('choice_submissions as s',fn($j)=>$j->on('s.event_candidate_id','=','c.id')->where('s.status','SUBMITTED'))->where('c.choice_event_id',$event->id)->where('p.choice_event_id',$event->id);
        if ($submittedOnly) $q->whereNotNull('s.id');
        // One row per application, matching the event's post eligibility/import structure.
        return $q->orderBy('p.id')->orderBy('a.id')->get(['a.user','a.reg','c.name','c.fname','c.mname','c.b_date','s.id as submission_id','s.candidate_snapshot','s.submitted_choices','s.unselected_choices'])->map(function ($row) {
            $snapshot=$row->candidate_snapshot ? json_decode($row->candidate_snapshot,true) : [];
            return ['user'=>(string)$row->user,'reg'=>(string)$row->reg,'name'=>$snapshot['name']??$row->name,'fname'=>array_key_exists('fname',$snapshot) ? ($snapshot['fname']??'') : ($row->fname??''),'mname'=>array_key_exists('mname',$snapshot) ? ($snapshot['mname']??'') : ($row->mname??''),'b_date'=>$snapshot['b_date']??$row->b_date,'submitted_choices'=>$row->submission_id ? (string)$row->submitted_choices : '', 'unselected_choices'=>$row->submission_id ? (string)$row->unselected_choices : '', 'submission_status'=>(bool)$row->submission_id];
        })->all();
    }
    public function choices(ChoiceEvent $event): array {
        return $event->choices()->with('post')->get()->map(fn($c)=>['order'=>$c->sort_order,'code'=>$c->code,'title'=>$c->title,'post_count'=>$c->post_count,'post_code'=>$c->post?->post_code,'post_title'=>$c->post?->title,'organization'=>$c->post?->organization,'ministry'=>$c->post?->ministry])->all();
    }
}
