<?php
namespace App\Http\Controllers;
use App\Models\{ChoiceEvent,EventPost,ChoiceOption};
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class ChoiceEventController extends Controller {
    public function index(Request $r) {
        $archive = $r->boolean('archive');
        $events = ChoiceEvent::query()->withCount(['posts','candidates'])->when($archive, fn($q)=>$q->where(fn($q)=>$q->where('end_at','<',now())->orWhereIn('status',['ARCHIVED','CANCELLED'])),fn($q)=>$q->where('end_at','>=',now())->whereNotIn('status',['ARCHIVED','CANCELLED']))->latest()->paginate(15)->withQueryString();
        return view('choice.events.index', compact('events','archive'));
    }
    public function create() { return view('choice.events.form', ['event'=>new ChoiceEvent]); }
    public function store(Request $r) {
        $data = $this->validateEvent($r); $data['created_by']=$r->user()->id;
        $event = DB::transaction(function () use ($r,$data) {
            $event=ChoiceEvent::create($data);
            if (!$event->multiple_posts) $event->posts()->create(['post_code'=>$event->post_code,'title'=>$event->title]);
            $this->audit($r,$event,'EVENT_CREATED',$data); return $event;
        });
        return redirect()->route('choice-events.show',$event)->with('success','Event created. Add choices and import candidates.');
    }
    public function show(ChoiceEvent $choiceEvent) {
        $choiceEvent->load(['posts'=>fn($q)=>$q->withCount('applications')->with('choices')]);
        return view('choice.events.show',['event'=>$choiceEvent]);
    }
    public function edit(ChoiceEvent $choiceEvent) {
        abort_if($choiceEvent->lifecycle==='ARCHIVED',403,'Archived events are read-only.');
        return view('choice.events.form',['event'=>$choiceEvent]);
    }
    public function update(Request $r, ChoiceEvent $choiceEvent) {
        $data=$this->validateEvent($r);
        DB::transaction(function () use ($r,$choiceEvent,$data) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            abort_if($event->lifecycle==='ARCHIVED',403,'Archived events are read-only.');
            $old=$event->only(array_keys($data)); $event->update($data); $this->audit($r,$event,'EVENT_UPDATED',['old'=>$old,'new'=>$data]);
        });
        return redirect()->route('choice-events.show',$choiceEvent)->with('success','Event updated.');
    }
    public function storePost(Request $r, ChoiceEvent $choiceEvent) {
        $data=$r->validate(['post_code'=>['required','string','max:20','regex:/^[A-Za-z0-9_-]+$/',Rule::unique('event_posts')->where('choice_event_id',$choiceEvent->id)],'title'=>'required|string|max:255','organization'=>'nullable|string|max:255','ministry'=>'nullable|string|max:255']);
        DB::transaction(function () use ($r,$choiceEvent,$data) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail(); $this->draft($event);
            if (!$event->multiple_posts && $event->posts()->exists()) throw ValidationException::withMessages(['post_code'=>'Single-post events can contain only one post.']);
            $event->posts()->create($data); $this->audit($r,$event,'POST_CREATED',$data);
        });
        return back()->with('success','Post added.');
    }
    public function storeChoice(Request $r, ChoiceEvent $choiceEvent, EventPost $post) {
        abort_unless($post->choice_event_id===$choiceEvent->id,404);
        $data=$r->validate(['code'=>['required','string','max:20','regex:/^[A-Za-z0-9_-]+$/',Rule::unique('choice_options')->where('choice_event_id',$choiceEvent->id)],'title'=>'required|string|max:255','post_count'=>'nullable|integer|min:0','sort_order'=>'required|integer|min:1|max:100000']);
        DB::transaction(function () use ($r,$choiceEvent,$post,$data) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail(); $this->draft($event);
            $event->choices()->create($data+['event_post_id'=>$post->id]); $this->audit($r,$event,'CHOICE_CREATED',$data);
        });
        return back()->with('success','Choice added.');
    }
    public function destroy(Request $r, ChoiceEvent $choiceEvent) {
        abort_unless($r->user()->role===UserRole::Admin,403);
        $r->validate(['confirmation'=>['required',Rule::in(['DELETE'])]]);
        DB::transaction(function () use ($choiceEvent) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $candidateIds=$event->candidates()->select('id');
            $postIds=$event->posts()->select('id');
            $submissionIds=DB::table('choice_submissions')->whereIn('event_candidate_id',clone $candidateIds)->select('id');
            DB::table('choice_submission_items')->whereIn('choice_submission_id',$submissionIds)->delete();
            DB::table('choice_submissions')->whereIn('event_candidate_id',clone $candidateIds)->delete();
            DB::table('candidate_applications')->whereIn('event_post_id',clone $postIds)->delete();
            DB::table('candidate_imports')->whereIn('event_post_id',clone $postIds)->delete();
            DB::table('choice_audits')->where('choice_event_id',$event->id)->delete();
            $event->candidates()->delete();
            $event->choices()->delete();
            $event->posts()->delete();
            $event->delete();
        });
        return redirect()->route('choice-events.index')->with('success','Event and all associated data deleted.');
    }
    public function destroyChoice(Request $r, ChoiceEvent $choiceEvent, EventPost $post, ChoiceOption $choice) {
        abort_unless($r->user()->role===UserRole::Admin,403);
        abort_unless($post->choice_event_id===$choiceEvent->id && $choice->choice_event_id===$choiceEvent->id && $choice->event_post_id===$post->id,404);
        $r->validate(['confirmation'=>['required',Rule::in(['DELETE'])]]);
        DB::transaction(function () use ($r,$choiceEvent,$choice) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $this->draft($event);
            if (DB::table('choice_submission_items')->where('choice_option_id',$choice->id)->exists()) throw ValidationException::withMessages(['choice'=>'This choice is referenced by a submission and cannot be deleted individually.']);
            $details=$choice->only(['id','code','title']);
            $choice->delete();
            $this->audit($r,$event,'CHOICE_DELETED',$details);
        });
        return redirect()->route('choice-events.show',$choiceEvent)->with('success','Choice deleted.');
    }
    private function draft(ChoiceEvent $event): void { abort_unless($event->status==='DRAFT' && $event->end_at->gte(now()),403,'Configuration changes require a current draft event.'); }
    private function validateEvent(Request $r): array {
        $data=$r->validate(['title'=>'required|string|max:255','post_code'=>['required','string','max:20','regex:/^[A-Za-z0-9_-]+$/'],'unit'=>['required',Rule::in(array_keys(ChoiceEvent::unitOptions()))],'instructions'=>'nullable|string|max:3000','status'=>['required',Rule::in(array_keys(ChoiceEvent::statusOptions()))],'start_at'=>'required|date_format:Y-m-d\TH:i','end_at'=>'required|date_format:Y-m-d\TH:i|after:start_at','multiple_posts'=>'sometimes|boolean']);
        $data['multiple_posts']=$r->boolean('multiple_posts');
        if ($r->route('choiceEvent')) { $event=$r->route('choiceEvent'); if ($data['multiple_posts']!==$event->multiple_posts) throw ValidationException::withMessages(['multiple_posts'=>'Eligibility mode cannot be changed after creation.']); }
        return $data;
    }
    private function audit(Request $r, ChoiceEvent $event, string $action, array $details): void {
        DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$r->user()->id,'action'=>$action,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);
    }
}
