<?php
namespace App\Http\Controllers;
use App\Enums\UserRole;
use App\Models\ChoiceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\Rule;
class ChoiceDataResetController extends Controller {
    private const TABLES=['choice_submission_items','choice_submissions','candidate_applications','candidate_imports','choice_audits','event_candidates','choice_options','event_posts','choice_events'];
    private function admin(Request $request): void { abort_unless($request->user()->role===UserRole::Admin,403); }
    public function confirm(Request $request) {
        $this->admin($request);
        $counts=[];
        foreach (self::TABLES as $table) $counts[$table]=DB::table($table)->count();
        return view('choice.events.reset',['counts'=>$counts]);
    }
    public function destroy(Request $request) {
        $this->admin($request);
        $request->validate(['confirmation'=>['required',Rule::in(['RESET ALL'])],'acknowledge'=>'accepted']);
        $counts=DB::transaction(function () {
            // Match event-level write locks used by submission/import administration.
            ChoiceEvent::orderBy('id')->lockForUpdate()->get();
            $counts=[];
            foreach (self::TABLES as $table) $counts[$table]=DB::table($table)->delete();
            return $counts;
        });
        Log::notice('All choice data reset',['administrator_id'=>$request->user()->id,'deleted'=>$counts]);
        $request->session()->forget(['candidate_access','candidate_review','choice_import','choice_xlsx']);
        return redirect()->route('choice-events.index')->with('success','All choice events and related data have been deleted. Staff accounts are retained.');
    }
}
