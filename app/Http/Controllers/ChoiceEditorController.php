<?php
namespace App\Http\Controllers;
use App\Models\{ChoiceEvent,EventPost};
use App\Services\Choice\{ChoiceEditor,ChoiceWorkbook};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class ChoiceEditorController extends Controller {
    public function save(Request $r,ChoiceEvent $choiceEvent,EventPost $post,ChoiceEditor $editor) {
        abort_unless($post->choice_event_id===$choiceEvent->id,404);
        $data=$r->validate(['rows'=>'required|array','revision'=>'required|string|size:64']);
        $editor->save($choiceEvent,$post,$data['rows'],$data['revision'],$r->user()->id);
        return redirect()->route('choice-events.show',$choiceEvent)->with('success','Choices and their order saved.');
    }
    public function sample(ChoiceEvent $choiceEvent,EventPost $post,ChoiceWorkbook $workbook) {
        abort_unless($post->choice_event_id===$choiceEvent->id,404);
        $book=$workbook->sample();
        return response()->streamDownload(function () use ($book) {
            try { (new Xlsx($book))->save('php://output'); } finally { $book->disconnectWorksheets(); }
        },'choice-options-sample_'.now()->format('Ymd_His').'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
    public function preview(Request $r,ChoiceEvent $choiceEvent,EventPost $post,ChoiceEditor $editor,ChoiceWorkbook $workbook) {
        $editor->editable($choiceEvent,$post);
        $key=$this->key($choiceEvent,$post); $r->session()->forget($key);
        $r->validate(['xlsx'=>'required|file|mimes:xlsx|max:2048']);
        $rows=$workbook->read($r->file('xlsx')->getRealPath());
        $codes=$choiceEvent->choices()->pluck('code')->map(fn($v)=>mb_strtolower($v))->flip();
        foreach ($rows as $row) if ($codes->has(mb_strtolower($row['code']))) throw ValidationException::withMessages(['xlsx'=>'Choice code already exists: '.$row['code']]);
        if ($post->choices()->count()+count($rows)>ChoiceEditor::MAX_ROWS) throw ValidationException::withMessages(['xlsx'=>'Maximum 500 choices per post.']);
        $nonce=Str::random(40);
        $r->session()->put($key,['rows'=>$rows,'revision'=>$editor->revision($post),'nonce'=>$nonce,'expires'=>now()->addMinutes(20)->timestamp]);
        return view('choice.options.preview',['event'=>$choiceEvent,'post'=>$post,'rows'=>$rows,'nonce'=>$nonce]);
    }
    public function confirm(Request $r,ChoiceEvent $choiceEvent,EventPost $post,ChoiceEditor $editor) {
        abort_unless($post->choice_event_id===$choiceEvent->id,404);
        $r->validate(['nonce'=>'required|string']); $key=$this->key($choiceEvent,$post); $preview=$r->session()->get($key);
        if (!$preview || $preview['expires']<now()->timestamp || !hash_equals($preview['nonce'],(string)$r->input('nonce'))) throw ValidationException::withMessages(['xlsx'=>'Preview expired. Upload again.']);
        $editor->save($choiceEvent,$post,$preview['rows'],$preview['revision'],$r->user()->id,true);
        $r->session()->forget($key);
        return redirect()->route('choice-events.show',$choiceEvent)->with('success',count($preview['rows']).' choices imported.');
    }
    private function key(ChoiceEvent $event,EventPost $post): string { return 'choice_xlsx.'.$event->id.'.'.$post->id; }
}
