<?php
namespace App\Services\Choice;
use App\Models\{ChoiceEvent,EventPost};
use Illuminate\Support\Facades\DB;
class MultiplePostExport {
    /** Only persisted, administrator-confirmed application links are exported. */
    public function dataset(ChoiceEvent $event,?EventPost $post,ChoiceExportData $data,bool $unique=false): array {
        $others=$event->posts()->when($post,fn($q)=>$q->whereKeyNot($post->id),fn($q)=>$q->orderBy('post_code'))->orderBy('id')->get();
        $columns=['user','reg','name','fname','mname','b_date','ssc_roll','ssc_year','hsc_roll','hsc_year','nid','submitted_choices','unselected_choices','submission_status','applied_post_code'];
        $mapping=['SSC_ROLL'=>'ssc_roll','SSC_YEAR'=>'ssc_year','HSC_ROLL'=>'hsc_roll','HSC_YEAR'=>'hsc_year','NID'=>'nid'];
        $used=array_fill_keys(array_keys($mapping),true);
        foreach ($others as $other) foreach (['reg'=>'REG','user'=>'USR'] as $key=>$prefix) {
            $column=$key.'_'.$other->post_code; $columns[]=$column;
            $field=$prefix.'_'.strtoupper($other->post_code);
            if (strlen($field)>10 || !preg_match('/^[A-Z_][A-Z0-9_]*$/',$field) || isset($used[$field])) $field=($key==='reg' ? 'R':'U').'_P'.$other->id;
            if (strlen($field)>10 || isset($used[$field])) throw new \RuntimeException('Cannot create a unique DBF column for post '.$other->post_code.'.');
            $mapping[$field]=$column; $used[$field]=true;
        }
        if ($post) $applications=$post->applications()->with('candidate')->orderBy('id')->get();
        else $applications=\App\Models\CandidateApplication::query()->select('candidate_applications.*')
            ->join('event_posts as export_posts','export_posts.id','=','candidate_applications.event_post_id')
            ->where('export_posts.choice_event_id',$event->id)->with('candidate')
            ->orderBy('export_posts.post_code')->orderBy('candidate_applications.reg')->orderBy('candidate_applications.id')->get();
        // Keep the first sorted application for each confirmed person, never deduplicate USER/REG.
        if ($unique) $applications=$applications->unique('event_candidate_id')->values();
        // A SQL subquery avoids one bound parameter per application in large combined datasets.
        $selectedCandidates=DB::table('candidate_applications as selected')->join('event_posts as selected_posts','selected_posts.id','=','selected.event_post_id')
            ->where('selected_posts.choice_event_id',$event->id)->when($post,fn($q)=>$q->where('selected.event_post_id',$post->id))->select('selected.event_candidate_id');
        $links=DB::table('candidate_applications as a')->join('event_posts as p','p.id','=','a.event_post_id')
            ->where('p.choice_event_id',$event->id)->whereIn('a.event_candidate_id',$selectedCandidates)
            ->orderBy('a.id')->get(['a.event_candidate_id','a.event_post_id','a.user','a.reg'])->groupBy('event_candidate_id');
        $base=[];
        foreach ($data->candidates($event,false,$post?->id) as $row) $base[json_encode([$row['applied_post_code'],$row['user'],$row['reg']])]=$row;
        $postCodes=$event->posts()->pluck('post_code','id');
        $rows=[];
        foreach ($applications as $app) {
            $row=$base[json_encode([$postCodes[$app->event_post_id],(string)$app->user,(string)$app->reg])];
            $identity=$app->source_identity ? json_decode($app->source_identity,true) : [];
            foreach (['ssc_roll','ssc_year','hsc_roll','hsc_year','nid'] as $key) $row[$key]=(string)(array_key_exists($key,$identity) ? ($identity[$key]??'') : ($app->candidate->$key??''));
            $personLinks=($links[$app->event_candidate_id]??collect())->keyBy('event_post_id');
            foreach ($others as $other) foreach (['reg','user'] as $key) $row[$key.'_'.$other->post_code]=(string)($personLinks->get($other->id)?->$key??'');
            $rows[]=$row;
        }
        return compact('columns','rows','mapping');
    }
}
