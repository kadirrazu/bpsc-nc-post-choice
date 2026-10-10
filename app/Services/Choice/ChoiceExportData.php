<?php
namespace App\Services\Choice;
use App\Models\ChoiceEvent;
use Illuminate\Support\Facades\DB;
class ChoiceExportData {
    public function summary(ChoiceEvent $event): array {
        // Count people once, even when they have multiple post applications or cancelled history.
        $counts=DB::table('event_candidates as c')->where('c.choice_event_id',$event->id)
            ->selectRaw('COUNT(*) as total_candidates')
            ->selectRaw("COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM choice_submissions s WHERE s.event_candidate_id = c.id AND s.status = ?) THEN 1 ELSE 0 END), 0) as submitted_candidates", ['SUBMITTED'])->first();
        return ['total_candidates'=>(int)$counts->total_candidates,'submitted_candidates'=>(int)$counts->submitted_candidates];
    }
    public function postSummary(ChoiceEvent $event): array {
        return $event->posts()->orderBy('id')->withCount('applications')
            ->withCount(['applications as submitted_candidates'=>fn($q)=>$q->whereExists(fn($s)=>$s->selectRaw('1')->from('choice_submissions')->whereColumn('choice_submissions.event_candidate_id','candidate_applications.event_candidate_id')->where('status','SUBMITTED'))])
            ->get()->map(fn($p)=>['post_id'=>$p->id,'post_code'=>$p->post_code,'post_title'=>$p->title,'total_candidates'=>$p->applications_count,'submitted_candidates'=>$p->submitted_candidates,'not_submitted'=>$p->applications_count-$p->submitted_candidates])->all();
    }
    public function candidates(ChoiceEvent $event,bool $submittedOnly=false,?int $postId=null): array {
        $q=DB::table('candidate_applications as a')->join('event_candidates as c','c.id','=','a.event_candidate_id')->join('event_posts as p','p.id','=','a.event_post_id')->leftJoin('choice_submissions as s',fn($j)=>$j->on('s.event_candidate_id','=','c.id')->where('s.status','SUBMITTED'))->where('c.choice_event_id',$event->id)->where('p.choice_event_id',$event->id);
        if ($submittedOnly) $q->whereNotNull('s.id');
        if ($event->multiple_posts && $postId) $q->where('p.id',$postId);
        // One row per application, matching the event's post eligibility/import structure.
        return $q->orderBy('p.id')->orderBy('a.id')->get(['a.user','a.reg','c.name','c.fname','c.mname','c.b_date','s.id as submission_id','s.candidate_snapshot','a.source_identity','p.post_code as applied_post_code','s.submitted_choices','s.unselected_choices'])->map(function ($row) use ($event) {
            $snapshot=$row->candidate_snapshot ? json_decode($row->candidate_snapshot,true) : [];
            if ($event->multiple_posts) $snapshot=$row->source_identity ? json_decode($row->source_identity,true) : []; // Preserve each applied post's original identity in exports.
            $result=['user'=>(string)$row->user,'reg'=>(string)$row->reg,'name'=>$snapshot['name']??$row->name,'fname'=>array_key_exists('fname',$snapshot) ? ($snapshot['fname']??'') : ($row->fname??''),'mname'=>array_key_exists('mname',$snapshot) ? ($snapshot['mname']??'') : ($row->mname??''),'b_date'=>$snapshot['b_date']??$row->b_date,'submitted_choices'=>$row->submission_id ? (string)$row->submitted_choices : '', 'unselected_choices'=>$row->submission_id ? (string)$row->unselected_choices : '', 'submission_status'=>(bool)$row->submission_id];
            if ($event->multiple_posts) $result['applied_post_code']=$row->applied_post_code;
            return $result;
        })->all();
    }
    public function choices(ChoiceEvent $event): array {
        return $event->choices()->with('post')->get()->map(fn($c)=>['order'=>$c->sort_order,'code'=>$c->code,'title'=>$c->title,'post_count'=>$c->post_count,'post_code'=>$c->post?->post_code,'post_title'=>$c->post?->title,'organization'=>$c->post?->organization,'ministry'=>$c->post?->ministry])->all();
    }
}
