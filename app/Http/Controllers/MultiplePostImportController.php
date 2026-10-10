<?php
namespace App\Http\Controllers;
use App\Models\{ChoiceEvent,EventPost,EventCandidate};
use App\Services\Choice\{CandidateCsvReader,CandidateExcelReader,MultiplePostMatcher,ExportFilename};
use Illuminate\Http\Request;
use Illuminate\Support\{Facades\DB,Str};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\{Cell\DataType,Writer\Xlsx};
class MultiplePostImportController extends Controller {
    private function check(ChoiceEvent $event,EventPost $post): void {
        abort_unless($post->choice_event_id===$event->id && $event->multiple_posts,404);
        abort_unless(in_array($event->status,['DRAFT','PUBLISHED'],true) && $event->end_at->gte(now()),403,'Imports require a current Draft or Published event.');
        abort_if(DB::table('choice_submissions')->whereIn('event_candidate_id',$event->candidates()->select('id'))->exists(),403,'Imports are locked once submissions exist.');
    }
    private function key(ChoiceEvent $event,EventPost $post): string { return 'multiple_import.'.$event->id.'.'.$post->id; }
    private function pending(Request $r,ChoiceEvent $event,EventPost $post): array {
        $this->check($event,$post); $pending=$r->session()->get($this->key($event,$post));
        abort_unless(is_array($pending) && $pending['expires']>=now()->timestamp,422,'Preview expired. Upload the file again.');
        return $pending;
    }
    public function preview(Request $r,ChoiceEvent $choiceEvent,EventPost $post,MultiplePostMatcher $matcher) {
        $this->check($choiceEvent,$post); $r->session()->forget($this->key($choiceEvent,$post));
        $r->validate(['file'=>'required|file|mimes:csv,txt,xls,xlsx|max:5120']);
        $extension=strtolower($r->file('file')->getClientOriginalExtension());
        if (!in_array($extension,['csv','txt','xls','xlsx'],true)) throw ValidationException::withMessages(['file'=>'Upload CSV, XLS or XLSX.']);
        try { $result=in_array($extension,['xls','xlsx'],true) ? (new CandidateExcelReader)->read($r->file('file')->getRealPath(),$extension) : (new CandidateCsvReader)->read($r->file('file')->getRealPath()); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['file'=>$e->getMessage()]); }
        $existing=$post->applications()->get(['user','reg']);
        $users=$existing->pluck('user')->map(fn($v)=>mb_strtolower($v))->flip(); $regs=$existing->pluck('reg')->map(fn($v)=>mb_strtolower($v))->flip();
        foreach ($result['rows'] as $i=>$row) if ($users->has(mb_strtolower($row['user'])) || $regs->has(mb_strtolower($row['reg']))) $result['errors'][]='Row '.($result['row_numbers'][$i]??$i+2).': user or reg already exists for this post.';
        if ($result['errors']) throw ValidationException::withMessages(['file'=>array_slice($result['errors'],0,100)]);
        $catalog=$matcher->catalog($choiceEvent); $plan=$matcher->plan($result['rows'],$catalog,$result['row_numbers']);
        $alreadyApplied=$post->applications()->pluck('event_candidate_id')->all(); $exactTargets=[];
        foreach ($plan as $item) if ($item['kind']==='exact') {
            $id=$item['matches'][0];
            if (in_array($id,$alreadyApplied,true) || isset($exactTargets[$id])) throw ValidationException::withMessages(['file'=>'Row '.$item['line'].': this matched candidate already has an application for this post, or appears more than once in this file.']);
            $exactTargets[$id]=true;
        }
        // Partial matching is advisory; no candidate/application is written during preview or review.
        $r->session()->put($this->key($choiceEvent,$post),['plan'=>$plan,'filename'=>$r->file('file')->getClientOriginalName(),'nonce'=>Str::random(40),'expires'=>now()->addMinutes(60)->timestamp,'revision'=>$matcher->revision($choiceEvent,$catalog)]);
        return redirect()->route('choice-multiple.review',[$choiceEvent,$post]);
    }
    public function review(Request $r,ChoiceEvent $choiceEvent,EventPost $post,MultiplePostMatcher $matcher) {
        $pending=$this->pending($r,$choiceEvent,$post); $catalog=$matcher->catalog($choiceEvent);
        if (!hash_equals($pending['revision'],$matcher->revision($choiceEvent,$catalog))) {
            $r->session()->forget($this->key($choiceEvent,$post));
            return redirect()->route('choice-import.index',[$choiceEvent,$post])->withErrors(['file'=>'Event candidate data changed. Upload the file again.']);
        }
        $page=max(1,(int)$r->query('page',1)); $perPage=25; $rows=new LengthAwarePaginator(array_slice($pending['plan'],($page-1)*$perPage,$perPage,true),count($pending['plan']),$perPage,$page,['path'=>route('choice-multiple.review',[$choiceEvent,$post])]);
        $stats=['new'=>0,'exact'=>0,'review'=>0,'pending'=>0,'skipped'=>0];
        foreach ($pending['plan'] as $item) { $stats[$item['kind']]++; if (!$item['decision']) $stats['pending']++; if ($item['decision']==='skip') $stats['skipped']++; }
        return view('choice.multiple.review',['event'=>$choiceEvent,'post'=>$post,'rows'=>$rows,'people'=>array_column($catalog,null,'id'),'fields'=>MultiplePostMatcher::IDENTITY,'pending'=>$pending,'stats'=>$stats]);
    }
    public function decisions(Request $r,ChoiceEvent $choiceEvent,EventPost $post) {
        $pending=$this->pending($r,$choiceEvent,$post);
        $r->validate(['nonce'=>'required|string','decisions'=>'required|array|max:25','decisions.*'=>'nullable|string|max:40','page'=>'nullable|integer|min:1']);
        abort_unless(hash_equals($pending['nonce'],(string)$r->input('nonce')),422,'Invalid preview.');
        foreach ($r->input('decisions') as $i=>$decision) {
            $item=$pending['plan'][$i]??null;
            if (!$item || $item['kind']!=='review') throw ValidationException::withMessages(['decisions'=>'Invalid review row.']);
            $allowed=array_merge(['new','skip',''],array_map(fn($id)=>'link:'.$id,$item['matches']));
            if (!in_array($decision??'',$allowed,true)) throw ValidationException::withMessages(['decisions'=>'Select a suggested candidate, create a new candidate, or skip the row.']);
            $pending['plan'][$i]['decision']=$decision ?: null;
        }
        $r->session()->put($this->key($choiceEvent,$post),$pending);
        return redirect()->route('choice-multiple.review',[$choiceEvent,$post,'page'=>$r->integer('page',1)])->with('success','Review decisions saved. No candidate records have been imported yet.');
    }
    public function confirm(Request $r,ChoiceEvent $choiceEvent,EventPost $post,MultiplePostMatcher $matcher) {
        $pending=$this->pending($r,$choiceEvent,$post); $r->validate(['nonce'=>'required|string','confirmation'=>'accepted']);
        abort_unless(hash_equals($pending['nonce'],(string)$r->input('nonce')),422,'Invalid preview.');
        foreach ($pending['plan'] as $item) if (!$item['decision']) throw ValidationException::withMessages(['review'=>'Resolve all review rows and save the decisions before importing.']);
        $counts=DB::transaction(function () use ($r,$choiceEvent,$post,$pending,$matcher) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail(); $this->check($event,$post);
            $catalog=$matcher->catalog($event);
            if (!hash_equals($pending['revision'],$matcher->revision($event,$catalog))) throw ValidationException::withMessages(['file'=>'Event candidate data changed after preview. Upload the file again.']);
            $counts=['new'=>0,'linked'=>0,'skipped'=>0]; $auditRows=[];
            foreach ($pending['plan'] as $item) {
                $row=$item['row']; $decision=$item['decision'];
                if ($decision==='skip') { $counts['skipped']++; $auditRows[]=['line'=>$item['line'],'decision'=>'skip']; continue; }
                if ($post->applications()->where(fn($q)=>$q->where('user',$row['user'])->orWhere('reg',$row['reg']))->exists()) throw ValidationException::withMessages(['file'=>'Duplicate user or registration in this post. Upload again.']);
                $identity=array_intersect_key($row,array_flip(MultiplePostMatcher::IDENTITY));
                if ($decision==='new') { $candidate=$event->candidates()->create($identity); $counts['new']++; }
                else {
                    $id=(int)substr($decision,5);
                    if (!in_array($id,$item['matches'],true)) throw ValidationException::withMessages(['review'=>'Invalid candidate link.']);
                    $candidate=$event->candidates()->whereKey($id)->firstOrFail();
                    if ($candidate->applications()->where('event_post_id',$post->id)->exists()) throw ValidationException::withMessages(['review'=>'Row '.$item['line'].': this candidate already has an application for this post.']);
                    $counts['linked']++;
                    // Preserve established identity; fill only absent optional supporting fields.
                    foreach (array_diff(MultiplePostMatcher::IDENTITY,MultiplePostMatcher::EXACT) as $field) if (empty($candidate->$field) && !empty($identity[$field])) $candidate->$field=$identity[$field];
                    $candidate->save();
                }
                // USER is not an identity match. Reject sign-in ambiguity across distinct candidates.
                $birthDates=array_unique([$candidate->b_date->format('Y-m-d'),$row['b_date']]);
                $collision=\App\Models\CandidateApplication::with('candidate')->where('user',$row['user'])->whereHas('candidate',fn($q)=>$q->where('choice_event_id',$event->id)->where('id','!=',$candidate->id))->get()->contains(function ($existing) use ($birthDates) {
                    $source=$existing->source_identity ? json_decode($existing->source_identity,true) : [];
                    return (bool)array_intersect($birthDates,array_filter([$existing->candidate->b_date->format('Y-m-d'),$source['b_date']??null]));
                });
                if ($collision) throw ValidationException::withMessages(['review'=>'Row '.$item['line'].': this User ID and birth date belong to another candidate in this event. Correct the file or review the identity.']);
                $application=$post->applications()->create(array_diff_key($row,array_flip(MultiplePostMatcher::IDENTITY))+['event_candidate_id'=>$candidate->id,'source_identity'=>json_encode($identity,JSON_UNESCAPED_UNICODE)]);
                $auditRows[]=['line'=>$item['line'],'candidate_id'=>$candidate->id,'application_id'=>$application->id,'kind'=>$item['kind'],'decision'=>$decision];
            }
            if (!$counts['new'] && !$counts['linked']) throw ValidationException::withMessages(['review'=>'All rows are skipped. Upload a corrected file instead.']);
            DB::table('candidate_imports')->insert(['event_post_id'=>$post->id,'filename'=>$pending['filename'],'row_count'=>$counts['new']+$counts['linked'],'imported_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$r->user()->id,'action'=>'MULTIPLE_CANDIDATES_IMPORTED','details'=>json_encode(['post_id'=>$post->id,'filename'=>$pending['filename'],'counts'=>$counts,'rows'=>$auditRows,'ip'=>$r->ip()],JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);
            return $counts;
        });
        $r->session()->forget($this->key($choiceEvent,$post));
        return redirect()->route('choice-import.index',[$choiceEvent,$post])->with('success',$counts['new'].' new candidates, '.$counts['linked'].' linked applications and '.$counts['skipped'].' skipped rows.');
    }
    public function sample(Request $r,ChoiceEvent $choiceEvent,EventPost $post) {
        abort_unless($post->choice_event_id===$choiceEvent->id && $choiceEvent->multiple_posts,404);
        $book=(new CandidateExcelReader)->sample(); $sheet=$book->getActiveSheet();
        foreach (range(1,17) as $col) {
            $header=(string)$sheet->getCell([$col,1])->getValue();
            if (in_array($header,array_merge(['user','reg'],MultiplePostMatcher::EXACT),true)) $sheet->getStyle([$col,1])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFE5D5');
            if ($header==='post_code') $sheet->setCellValueExplicit([$col,2],$post->post_code,DataType::TYPE_STRING);
            if ($header==='post_name') $sheet->setCellValueExplicit([$col,2],$post->title,DataType::TYPE_STRING);
        }
        return response()->streamDownload(function () use ($book) { try { (new Xlsx($book))->save('php://output'); } finally { $book->disconnectWorksheets(); } },ExportFilename::make('multiple-candidate-sample',$post->post_code,'xlsx'),['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
