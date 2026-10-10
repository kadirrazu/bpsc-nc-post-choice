<?php
namespace App\Http\Controllers;
use App\Models\{ChoiceEvent,EventCandidate,CandidateApplication};
use App\Services\Choice\{BirthDateNormalizer,SubmissionReceiptPdf,ExportFilename,ReceiptQrCode,SubmissionReceiptData};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class CandidateSubmissionController extends Controller {
    private function available(ChoiceEvent $event): void {
        abort_unless($event->status==='PUBLISHED' && $event->start_at->lte(now()) && $event->end_at->gte(now()),403,'This event is not accepting choices now.');
    }
    private function key(ChoiceEvent $event): string { return 'candidate_access.'.$event->id; }
    private function signedInPerson(Request $r,ChoiceEvent $event): ?EventCandidate {
        $access=$r->session()->get($this->key($event));
        if (!is_array($access) || !isset($access['expires'],$access['candidate_id']) || !is_numeric($access['candidate_id']) || (int)$access['candidate_id']<1 || !is_numeric($access['expires']) || (int)$access['expires']<now()->timestamp) return null;
        return $event->candidates()->find((int)$access['candidate_id']);
    }
    private function person(Request $r,ChoiceEvent $event): EventCandidate {
        $person=$this->signedInPerson($r,$event);
        if (!$person) abort(403,'Your candidate session expired. Return to the event sign-in page.');
        return $person;
    }
    private function eligible(ChoiceEvent $event,EventCandidate $person) {
        return $event->choices()->whereIn('event_post_id',$person->applications()->select('event_post_id'))->get();
    }
    private function existing(EventCandidate $person) {
        return DB::table('choice_submissions')->where('event_candidate_id',$person->id)->where('status','SUBMITTED')->first();
    }
    public function login(Request $r,ChoiceEvent $choiceEvent) {
        if ($this->signedInPerson($r,$choiceEvent)) return redirect()->route('candidate.choices',$choiceEvent);
        $r->session()->forget([$this->key($choiceEvent),'candidate_review.'.$choiceEvent->id]);
        $this->available($choiceEvent);
        return view('choice.candidate.login',['event'=>$choiceEvent]);
    }
    public function authenticate(Request $r,ChoiceEvent $choiceEvent) {
        if ($this->signedInPerson($r,$choiceEvent)) return redirect()->route('candidate.choices',$choiceEvent);
        $this->available($choiceEvent);
        $data=$r->validate(['user'=>'required|string|max:10','birth_date'=>['required','string','regex:/^\d{8}$/D']]);
        try { $date=(new BirthDateNormalizer)->normalize($data['birth_date']); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['credentials'=>'User ID and birth date did not match.']); }
        if ($choiceEvent->multiple_posts) {
            $matches=CandidateApplication::with('candidate')->whereHas('candidate',fn($q)=>$q->where('choice_event_id',$choiceEvent->id))->whereHas('post',fn($q)=>$q->where('choice_event_id',$choiceEvent->id))->where('user',$data['user'])->get()->filter(function ($app) use ($data,$date) {
                $source=$app->source_identity ? json_decode($app->source_identity,true) : [];
                return hash_equals((string)$app->user,$data['user']) && ($app->candidate->b_date->format('Y-m-d')===$date || ($source['b_date']??null)===$date);
            });
        } else {
            $matches=CandidateApplication::with('candidate')->whereHas('candidate',fn($q)=>$q->where('choice_event_id',$choiceEvent->id)->whereDate('b_date',$date))->whereHas('post',fn($q)=>$q->where('choice_event_id',$choiceEvent->id))->where('user',$data['user'])->get()->filter(fn($app)=>hash_equals((string)$app->user,$data['user']));
        }
        $ids=$matches->pluck('event_candidate_id')->unique();
        if ($ids->count()!==1) throw ValidationException::withMessages(['credentials'=>'User ID and birth date did not match.']);
        $r->session()->regenerate();
        $r->session()->put($this->key($choiceEvent),['candidate_id'=>$ids->first(),'application_id'=>$matches->first()->id,'expires'=>now()->addMinutes(30)->timestamp]);
        $r->session()->forget('candidate_review.'.$choiceEvent->id);
        return redirect()->route('candidate.choices',$choiceEvent);
    }
    public function choices(Request $r,ChoiceEvent $choiceEvent) {
        $person=$this->person($r,$choiceEvent); $submission=$this->existing($person);
        if ($submission) return $this->receipt($r,$choiceEvent,$person,$submission);
        $this->available($choiceEvent);
        $application=$person->applications()->whereHas('post',fn($q)=>$q->where('choice_event_id',$choiceEvent->id))->whereKey($r->session()->get($this->key($choiceEvent).'.application_id'))->first();
        $application ??= $person->applications()->whereHas('post',fn($q)=>$q->where('choice_event_id',$choiceEvent->id))->first();
        return view('choice.candidate.choices',['event'=>$choiceEvent,'person'=>$person,'application'=>$application,'options'=>$this->eligible($choiceEvent,$person),'selectedIds'=>$r->old('choices',$r->session()->get('candidate_review.'.$choiceEvent->id.'.ids',[]))]);
    }
    public function review(Request $r,ChoiceEvent $choiceEvent) {
        $this->available($choiceEvent); $person=$this->person($r,$choiceEvent);
        if ($this->existing($person)) return redirect()->route('candidate.choices',$choiceEvent);
        $data=$r->validate(['choices'=>'required|array|min:1|max:500','choices.*'=>'required|integer|distinct|min:1']);
        $ids=array_map('intval',$data['choices']); $options=$this->eligible($choiceEvent,$person)->keyBy('id');
        foreach ($ids as $id) if (!$options->has($id)) throw ValidationException::withMessages(['choices'=>'One or more choices are not applicable to your application.']);
        $selected=collect($ids)->map(fn($id)=>$options[$id]);
        $nonce=Str::random(40);
        $r->session()->put('candidate_review.'.$choiceEvent->id,['candidate_id'=>$person->id,'ids'=>$ids,'revision'=>hash('sha256',$options->toJson()),'nonce'=>$nonce,'expires'=>now()->addMinutes(20)->timestamp]);
        return view('choice.candidate.review',['event'=>$choiceEvent,'person'=>$person,'selected'=>$selected,'nonce'=>$nonce]);
    }
    public function submit(Request $r,ChoiceEvent $choiceEvent) {
        $person=$this->person($r,$choiceEvent);
        if ($this->existing($person)) return redirect()->route('candidate.choices',$choiceEvent);
        $r->validate(['nonce'=>'required|string','confirm'=>'accepted']);
        $review=$r->session()->get('candidate_review.'.$choiceEvent->id);
        if (!$review || $review['candidate_id']!==$person->id || $review['expires']<now()->timestamp || !hash_equals($review['nonce'],(string)$r->input('nonce'))) throw ValidationException::withMessages(['choices'=>'Review expired. Select and review your choices again.']);
        DB::transaction(function () use ($r,$choiceEvent,$person,$review) {
            $event=ChoiceEvent::whereKey($choiceEvent->id)->lockForUpdate()->firstOrFail();
            $this->available($event);
            $person=EventCandidate::where('choice_event_id',$event->id)->whereKey($person->id)->lockForUpdate()->firstOrFail();
            if ($this->existing($person)) return;
            $options=$this->eligible($event,$person)->keyBy('id');
            if (!hash_equals($review['revision'],hash('sha256',$options->toJson()))) throw ValidationException::withMessages(['choices'=>'Applicable choices changed. Review your choices again.']);
            $selected=collect($review['ids'])->map(fn($id)=>$options[$id]);
            $application=$this->application($r,$event,$person);
            $snapshot=['user'=>$application?->user,'reg'=>$application?->reg,'name'=>$person->name,'fname'=>$person->fname,'mname'=>$person->mname,'b_date'=>$person->b_date->format('Y-m-d')];
            $token=strtoupper(bin2hex(random_bytes(16)));
            $id=DB::table('choice_submissions')->insertGetId(['event_candidate_id'=>$person->id,'submitted_choices'=>$selected->pluck('code')->implode('|'),'unselected_choices'=>$options->except($review['ids'])->pluck('code')->implode('|'),'token'=>$token,'active_slot'=>1,'status'=>'SUBMITTED','submitted_at'=>now(),'submitted_ip'=>$r->ip(),'candidate_snapshot'=>json_encode($snapshot),'created_at'=>now(),'updated_at'=>now()]);
            foreach ($review['ids'] as $position=>$choiceId) DB::table('choice_submission_items')->insert(['choice_submission_id'=>$id,'choice_option_id'=>$choiceId,'preference_order'=>$position+1]);
        });
        $r->session()->forget('candidate_review.'.$choiceEvent->id);
        return redirect()->route('candidate.choices',$choiceEvent)->with('success','Your choices have been submitted successfully.');
    }
    private function application(Request $r,ChoiceEvent $event,EventCandidate $person) {
        $query=$person->applications()->whereHas('post',fn($q)=>$q->where('choice_event_id',$event->id));
        return (clone $query)->whereKey($r->session()->get($this->key($event).'.application_id'))->first() ?? $query->orderBy('id')->first();
    }
    private function receiptData(Request $r,ChoiceEvent $event,EventCandidate $person,$submission): array {
        $applicationId=$r->session()->get($this->key($event).'.application_id');
        return (new SubmissionReceiptData)->build($event,$person,$submission,$applicationId ? (int)$applicationId : null);
    }

    private function receipt(Request $r,ChoiceEvent $event,EventCandidate $person,$submission) {
        return view('choice.candidate.receipt',$this->receiptData($r,$event,$person,$submission));
    }
    public function pdf(Request $r,ChoiceEvent $choiceEvent,SubmissionReceiptPdf $pdf,ReceiptQrCode $qr) {
        $person=$this->person($r,$choiceEvent); $submission=$this->existing($person);
        abort_unless($submission,404,'No submitted choices found.');
        $data=$this->receiptData($r,$choiceEvent,$person,$submission);
        $data['qrDataUri']=$qr->dataUri($data['details']);
        $bytes=$pdf->render(view('choice.candidate.receipt-pdf',$data)->render(),$data['printTimestamp']);
        $filename=ExportFilename::make('choice-receipt',$choiceEvent->post_code,'pdf');
        return response($bytes,200,['Content-Type'=>'application/pdf','Content-Disposition'=>($r->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"','Cache-Control'=>'private, no-store']);
    }
    public function logout(Request $r,ChoiceEvent $choiceEvent) {
        $r->session()->forget([$this->key($choiceEvent),'candidate_review.'.$choiceEvent->id]);
        return redirect()->route('home');
    }
}
