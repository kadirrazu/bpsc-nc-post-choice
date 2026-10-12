<?php
namespace App\Http\Controllers;
use App\Enums\UserRole;
use App\Models\{ChoiceEvent,EventPost};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};
class EventPostController extends Controller {
    private function allowed(Request $r,ChoiceEvent $event,EventPost $post): void {
        abort_unless($r->user()->role===UserRole::Admin,403);
        abort_unless($event->multiple_posts && $post->choice_event_id===$event->id,404);
        if ($event->status!=='DRAFT' || $event->end_at->lt(now())) throw ValidationException::withMessages(['post'=>'Post changes require a current Draft event.']);
        if (DB::table('choice_submissions')->whereIn('event_candidate_id',$event->candidates()->select('id'))->exists()) throw ValidationException::withMessages(['post'=>'Posts are locked because submission records exist. Clear all event submissions before editing or deleting a post.']);
    }
    private function revision(EventPost $post): string {
        return hash('sha256',json_encode([$post->only(['id','post_code','title','organization','ministry','updated_at']),
            $post->applications()->orderBy('id')->get(['id','event_candidate_id','user','reg','updated_at']),
            $post->choices()->reorder('id')->get(['id','code','title','post_count','sort_order','updated_at']),
            DB::table('candidate_imports')->where('event_post_id',$post->id)->orderBy('id')->pluck('id')],JSON_UNESCAPED_UNICODE));
    }
    private function exclusive(ChoiceEvent $event,EventPost $post) {
        return $event->candidates()->whereHas('applications',fn($q)=>$q->where('event_post_id',$post->id))->whereDoesntHave('applications',fn($q)=>$q->where('event_post_id','!=',$post->id));
    }
    public function edit(Request $r,ChoiceEvent $choiceEvent,EventPost $post) {
        $this->allowed($r,$choiceEvent,$post);
        return view('choice.posts.edit',['event'=>$choiceEvent,'post'=>$post,'revision'=>$this->revision($post)]);
    }
    public function update(Request $r,ChoiceEvent $choiceEvent,EventPost $post) {
        $this->allowed($r,$choiceEvent,$post);
        $data=$r->validate(['revision'=>'required|string|size:64','post_code'=>['required','string','max:20','regex:/^[A-Za-z0-9_-]+$/',Rule::unique('event_posts')->where('choice_event_id',$choiceEvent->id)->ignore($post->id)],'title'=>'required|string|max:255','organization'=>'nullable|string|max:255','ministry'=>'nullable|string|max:255']);
        DB::transaction(function () use ($r,$choiceEvent,$post,$data) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $current=$event->posts()->whereKey($post->id)->lockForUpdate()->firstOrFail();$this->allowed($r,$event,$current);
            if (!hash_equals($this->revision($current),$data['revision'])) throw ValidationException::withMessages(['post'=>'Post data changed. Reload the edit page before saving.']);
            if ($event->posts()->where('post_code',$data['post_code'])->whereKeyNot($current->id)->exists()) throw ValidationException::withMessages(['post_code'=>'This post code is already used in this event.']);
            $before=$current->only(['post_code','title','organization','ministry']);unset($data['revision']);$current->update($data);
            $this->audit($r,$event,'POST_UPDATED',['post_id'=>$current->id,'old'=>$before,'new'=>$data]);
        });
        $r->session()->forget('multiple_import.'.$choiceEvent->id.'.'.$post->id);
        return redirect()->route('choice-events.show',$choiceEvent)->with('success','Post updated. Existing candidates and choices are preserved.');
    }
    public function confirmDelete(Request $r,ChoiceEvent $choiceEvent,EventPost $post) {
        $this->allowed($r,$choiceEvent,$post);
        return DB::transaction(function () use ($r,$choiceEvent,$post) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $current=$event->posts()->whereKey($post->id)->lockForUpdate()->firstOrFail();$this->allowed($r,$event,$current);
            $applications=$current->applications()->count();$exclusive=$this->exclusive($event,$current)->count();
            return view('choice.posts.delete',['event'=>$event,'post'=>$current,'revision'=>$this->revision($current),'applications'=>$applications,'exclusive'=>$exclusive,'shared'=>$current->applications()->distinct()->count('event_candidate_id')-$exclusive,'choices'=>$current->choices()->count()]);
        });
    }
    public function destroy(Request $r,ChoiceEvent $choiceEvent,EventPost $post) {
        $this->allowed($r,$choiceEvent,$post);$r->validate(['confirmation'=>['required',Rule::in(['DELETE'])],'revision'=>'required|string|size:64']);
        DB::transaction(function () use ($r,$choiceEvent,$post) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $current=$event->posts()->whereKey($post->id)->lockForUpdate()->firstOrFail();$this->allowed($r,$event,$current);
            if (!hash_equals($this->revision($current),$r->input('revision'))) throw ValidationException::withMessages(['post'=>'Post data changed. Reload the confirmation page and review the updated counts.']);
            if (DB::table('choice_submission_items')->whereIn('choice_option_id',$current->choices()->select('id'))->exists()) throw ValidationException::withMessages(['post'=>'This post has choices referenced by submission records and cannot be deleted.']);
            $exclusiveIds=$this->exclusive($event,$current)->pluck('id')->all();
            $details=$current->only(['id','post_code','title']);$details['applications_deleted']=$current->applications()->count();$details['candidates_deleted']=count($exclusiveIds);$details['choices_deleted']=$current->choices()->count();
            $current->applications()->delete();DB::table('candidate_imports')->where('event_post_id',$current->id)->delete();
            $current->choices()->delete();$event->candidates()->whereIntegerInRaw('id',$exclusiveIds)->delete();$current->delete();
            $this->audit($r,$event,'POST_DELETED',$details);
        });
        foreach (['multiple_import','choice_import','choice_xlsx'] as $prefix) $r->session()->forget($prefix.'.'.$choiceEvent->id.'.'.$post->id);
        return redirect()->route('choice-events.show',$choiceEvent)->with('success','Post and its candidate dataset deleted. Candidates linked to other posts are preserved.');
    }
    private function audit(Request $r,ChoiceEvent $event,string $action,array $details): void {
        DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$r->user()->id,'action'=>$action,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);
    }
}
