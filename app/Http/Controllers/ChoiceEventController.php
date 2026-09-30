<?php
namespace App\Http\Controllers;
use App\Models\{ChoiceEvent,EventPost};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class ChoiceEventController extends Controller {
    public function index(Request $r) {
        $archive = $r->boolean('archive');
        $events = ChoiceEvent::query()->withCount(['posts','candidates'])->when($archive, fn($q)=>$q->where('end_at','<',now()),fn($q)=>$q->where('end_at','>=',now()))->latest()->paginate(15)->withQueryString();
        return view('choice.events.index', compact('events','archive'));
    }
    public function create() { return view('choice.events.form', ['event'=>new ChoiceEvent]); }
    public function store(Request $r) {
        $data = $this->validateEvent($r); $data['created_by']=$r->user()->id;
        $event = DB::transaction(function () use ($r,$data) {
            $event=ChoiceEvent::create($data); $this->audit($r,$event,'EVENT_CREATED',$data); return $event;
        });
        return redirect()->route('choice-events.show',$event)->with('success','Event created. Add a post and its choices.');
    }
    public function show(ChoiceEvent $choiceEvent) {
        $choiceEvent->load(['posts'=>fn($q)=>$q->withCount('applications')->with('choices')]);
        return view('choice.events.show',['event'=>$choiceEvent]);
    }
    public function edit(ChoiceEvent $choiceEvent) {
        abort_if($choiceEvent->end_at->lt(now()),403,'Archived events are read-only.');
        return view('choice.events.form',['event'=>$choiceEvent]);
    }
    public function update(Request $r, ChoiceEvent $choiceEvent) {
        $data=$this->validateEvent($r);
        DB::transaction(function () use ($r,$choiceEvent,$data) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            abort_if($event->end_at->lt(now()),403,'Archived events are read-only.');
            if ($data['status']==='OPEN') {
                if (!$event->posts()->exists() || !$event->choices()->exists() || !$event->candidates()->exists()) throw ValidationException::withMessages(['status'=>'Add posts, choices and candidates before opening.']);
            }
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
    private function draft(ChoiceEvent $event): void { abort_unless($event->status==='DRAFT' && $event->end_at->gte(now()),403,'Configuration changes require a current draft event.'); }
    private function validateEvent(Request $r): array {
        $data=$r->validate(['title'=>'required|string|max:255','instructions'=>'nullable|string|max:3000','status'=>['required',Rule::in(['DRAFT','OPEN'])],'start_at'=>'required|date_format:Y-m-d\TH:i','end_at'=>'required|date_format:Y-m-d\TH:i|after:start_at','multiple_posts'=>'sometimes|boolean']);
        $data['multiple_posts']=$r->boolean('multiple_posts');
        if (!$r->route('choiceEvent')) { if ($data['status']!=='DRAFT') throw ValidationException::withMessages(['status'=>'Create the event as a draft first.']); }
        else { $event=$r->route('choiceEvent'); if ($data['multiple_posts']!==$event->multiple_posts) throw ValidationException::withMessages(['multiple_posts'=>'Eligibility mode cannot be changed after creation.']); }
        return $data;
    }
    private function audit(Request $r, ChoiceEvent $event, string $action, array $details): void {
        DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$r->user()->id,'action'=>$action,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);
    }
}
