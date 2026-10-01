<?php
namespace App\Services\Choice;
use App\Models\{ChoiceEvent,EventPost};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;
class ChoiceEditor {
    public const MAX_ROWS=500;
    public function editable(ChoiceEvent $event, EventPost $post): void {
        abort_unless($post->choice_event_id===$event->id,404);
        if ($event->status!=='DRAFT' || $event->end_at->lt(now())) throw ValidationException::withMessages(['choices'=>'Switch a current event to Draft before changing choices.']);
        if (DB::table('choice_submissions')->whereIn('event_candidate_id',$event->candidates()->select('id'))->exists()) throw ValidationException::withMessages(['choices'=>'Choices are locked because submissions exist.']);
    }
    public function revision(EventPost $post): string {
        return hash('sha256',$post->choices()->reorder('id')->get(['id','code','title','post_count','sort_order','updated_at'])->toJson());
    }
    public function rows(array $rows): array {
        Validator::make(['rows'=>$rows],[
            'rows'=>'required|array|min:1|max:'.self::MAX_ROWS,
            'rows.*'=>'array', 'rows.*.id'=>'nullable|integer|min:1|distinct',
            'rows.*.code'=>['required','string','max:20','regex:/^[A-Za-z0-9_-]+$/'],
            'rows.*.title'=>'required|string|max:255',
            'rows.*.post_count'=>'nullable|integer|min:0|max:4294967295',
            'rows.*.sort_order'=>'required|integer|min:1|max:100000|distinct',
        ])->validate();
        $result=[]; $seen=[];
        foreach ($rows as $row) {
            $code=trim($row['code']); $key=mb_strtolower($code);
            if (isset($seen[$key])) throw ValidationException::withMessages(['choices'=>'Duplicate choice code: '.$code]);
            $seen[$key]=true;
            $result[]=['id'=>empty($row['id']) ? null : (int)$row['id'],'code'=>$code,'title'=>trim($row['title']),'post_count'=>isset($row['post_count']) && $row['post_count']!=='' ? (int)$row['post_count'] : null,'sort_order'=>(int)$row['sort_order']];
        }
        usort($result,fn($a,$b)=>$a['sort_order']<=>$b['sort_order']);
        return $result;
    }
    public function save(ChoiceEvent $event,EventPost $post,array $input,string $revision,int $actor,bool $append=false): void {
        $rows=$this->rows($input);
        DB::transaction(function () use ($event,$post,$rows,$revision,$actor,$append) {
            $event=ChoiceEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->editable($event,$post);
            if (!hash_equals($this->revision($post),$revision)) throw ValidationException::withMessages(['choices'=>'Choices changed after you opened this page. Reload and try again.']);
            $existing=$post->choices()->get();
            if ($append) {
                if ($existing->count()+count($rows)>self::MAX_ROWS) throw ValidationException::withMessages(['choices'=>'Maximum 500 choices per post.']);
                foreach ($rows as $row) if ($row['id']!==null) throw ValidationException::withMessages(['choices'=>'Import can only add new choices.']);
            } else {
                $ids=array_values(array_filter(array_column($rows,'id'))); sort($ids);
                $expected=$existing->pluck('id')->all(); sort($expected);
                if ($ids!==$expected) throw ValidationException::withMessages(['choices'=>'All existing choices must be included. Use the separate Delete button to remove a choice.']);
            }
            $otherCodes=$event->choices()->when(!$append,fn($q)=>$q->where('event_post_id','!=',$post->id))->pluck('code')->map(fn($v)=>mb_strtolower($v))->flip();
            foreach ($rows as $row) if ($otherCodes->has(mb_strtolower($row['code']))) throw ValidationException::withMessages(['choices'=>'Choice code already exists in this event: '.$row['code']]);
            $before=$existing->toArray();
            // Temporary codes permit atomic swaps without violating the unique index.
            if (!$append) foreach ($existing as $choice) $choice->update(['code'=>'~'.base_convert((string)$choice->id,10,36)]);
            $order=$append ? (int)$existing->max('sort_order') : 0;
            foreach ($rows as $row) {
                $id=$row['id']; unset($row['id']); $row['sort_order']=++$order;
                if ($id) $post->choices()->whereKey($id)->firstOrFail()->update($row);
                else $post->choices()->create($row+['choice_event_id'=>$event->id]);
            }
            DB::table('choice_audits')->insert(['choice_event_id'=>$event->id,'actor_id'=>$actor,'action'=>$append ? 'CHOICES_IMPORTED' : 'CHOICES_SAVED','details'=>json_encode(['post_id'=>$post->id,'old'=>$before,'new'=>$post->choices()->get()->toArray()]),'created_at'=>now(),'updated_at'=>now()]);
        });
    }
}
