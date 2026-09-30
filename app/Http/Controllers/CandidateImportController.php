<?php
namespace App\Http\Controllers;
use App\Models\{ChoiceEvent,EventPost,EventCandidate};
use App\Services\Choice\CandidateCsvReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class CandidateImportController extends Controller {
    private function check(ChoiceEvent $event, EventPost $post): void {
        abort_unless($post->choice_event_id===$event->id,404);
        abort_unless(in_array($event->status,['DRAFT','PUBLISHED'],true) && $event->end_at->gte(now()),403,'Imports require a current Draft or Published event.');
        $candidateIds=$event->candidates()->select('id');
        abort_if(DB::table('choice_submissions')->whereIn('event_candidate_id',$candidateIds)->exists(),403,'Imports are locked once submissions exist.');
        abort_if($event->multiple_posts,422,'Multiple-post matching will be enabled in the next phase.');
    }
    public function index(ChoiceEvent $choiceEvent, EventPost $post) {
        abort_unless($post->choice_event_id===$choiceEvent->id,404);
        return view('choice.import.index',['event'=>$choiceEvent,'post'=>$post,'applications'=>$post->applications()->with('candidate')->latest()->paginate(25)]);
    }
    public function preview(Request $r, ChoiceEvent $choiceEvent, EventPost $post, CandidateCsvReader $reader) {
        $this->check($choiceEvent,$post);
        $r->validate(['file'=>'required|file|mimes:csv,txt|max:5120']);
        try { $result=$reader->read($r->file('file')->getRealPath()); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['file'=>$e->getMessage()]); }
        // One indexed query checks all uploaded identifiers against the selected post.
        $existing=$post->applications()->get(['user','reg']);
        $users=$existing->pluck('user')->map(fn($v)=>mb_strtolower($v))->flip();
        $regs=$existing->pluck('reg')->map(fn($v)=>mb_strtolower($v))->flip();
        foreach ($result['rows'] as $i=>$row) if ($users->has(mb_strtolower($row['user'])) || $regs->has(mb_strtolower($row['reg']))) $result['errors'][]='Row '.($i+2).': user or reg already exists for this post.';
        $key='choice_import.'.$choiceEvent->id.'.'.$post->id;
        $r->session()->forget($key);
        $nonce=Str::random(40);
        if (!$result['errors']) $r->session()->put($key,['rows'=>$result['rows'],'filename'=>$r->file('file')->getClientOriginalName(),'expires'=>now()->addMinutes(20)->timestamp,'nonce'=>$nonce]);
        return view('choice.import.preview',['event'=>$choiceEvent,'post'=>$post,'rows'=>$result['rows'],'importErrors'=>$result['errors'],'nonce'=>$nonce]);
    }
    public function confirm(Request $r, ChoiceEvent $choiceEvent, EventPost $post) {
        $this->check($choiceEvent,$post); $r->validate(['nonce'=>'required|string']);
        $key='choice_import.'.$choiceEvent->id.'.'.$post->id; $preview=$r->session()->get($key);
        abort_unless($preview && $preview['expires']>=now()->timestamp && hash_equals($preview['nonce'],$r->string('nonce')->toString()),422,'Preview expired. Upload the file again.');
        DB::transaction(function () use ($r,$choiceEvent,$post,$preview) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail(); $this->check($event,$post);
            foreach ($preview['rows'] as $row) {
                if ($post->applications()->where(fn($q)=>$q->where('user',$row['user'])->orWhere('reg',$row['reg']))->exists()) throw ValidationException::withMessages(['file'=>'Another import has added these identifiers. Upload again.']);
                $personKeys=['name','fname','mname','b_date','ssc_roll','ssc_year','hsc_roll','hsc_year','nid'];
                $person=array_intersect_key($row,array_flip($personKeys));
                $candidate=EventCandidate::create($person+['choice_event_id'=>$event->id]);
                $post->applications()->create(array_diff_key($row,array_flip($personKeys))+['event_candidate_id'=>$candidate->id]);
            }
            DB::table('candidate_imports')->insert(['event_post_id'=>$post->id,'filename'=>$preview['filename'],'row_count'=>count($preview['rows']),'imported_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$r->user()->id,'action'=>'CANDIDATES_IMPORTED','details'=>json_encode(['post_id'=>$post->id,'rows'=>count($preview['rows'])]),'created_at'=>now(),'updated_at'=>now()]);
        });
        $r->session()->forget($key);
        return redirect()->route('choice-import.index',[$choiceEvent,$post])->with('success',count($preview['rows']).' candidates imported.');
    }
    public function template() {
        return response()->streamDownload(function () { $out=fopen('php://output','w'); fputcsv($out,array_merge(CandidateCsvReader::REQUIRED,CandidateCsvReader::OPTIONAL),',','"',''); fclose($out); },'candidate-import-template.csv',['Content-Type'=>'text/csv']);
    }
}
