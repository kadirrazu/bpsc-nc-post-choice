<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{UserController,ChoiceEventController,CandidateImportController,ChoiceEditorController,CandidateSubmissionController};
use App\Http\Middleware\ChoiceStaff;
use App\Models\ChoiceEvent;
Route::get('/', fn()=>view('choice.public.home',['events'=>ChoiceEvent::available()->orderBy('end_at')->get()]))->name('home');
Route::get('/candidate/events/{choiceEvent}/sign-in',[CandidateSubmissionController::class,'login'])->name('candidate.login');
Route::post('/candidate/events/{choiceEvent}/sign-in',[CandidateSubmissionController::class,'authenticate'])->middleware('throttle:5,1')->name('candidate.authenticate');
Route::get('/candidate/events/{choiceEvent}/receipt.pdf',[CandidateSubmissionController::class,'pdf'])->middleware('throttle:10,1')->name('candidate.receipt-pdf');
Route::get('/candidate/events/{choiceEvent}/choices',[CandidateSubmissionController::class,'choices'])->name('candidate.choices');
Route::post('/candidate/events/{choiceEvent}/review',[CandidateSubmissionController::class,'review'])->middleware('throttle:20,1')->name('candidate.review');
Route::post('/candidate/events/{choiceEvent}/submit',[CandidateSubmissionController::class,'submit'])->middleware('throttle:10,1')->name('candidate.submit');
Route::post('/candidate/events/{choiceEvent}/sign-out',[CandidateSubmissionController::class,'logout'])->name('candidate.logout');
Route::middleware('auth')->group(function () {
    Route::view('/dashboard','dashboard.index')->name('dashboard');
    Route::resource('users',UserController::class)->except('destroy');
    Route::middleware(ChoiceStaff::class)->group(function () {
        Route::get('/choice-events', [ChoiceEventController::class,'index'])->name('choice-events.index');
        Route::get('/choice-events/create', [ChoiceEventController::class,'create'])->name('choice-events.create');
        Route::post('/choice-events', [ChoiceEventController::class,'store'])->name('choice-events.store');
        Route::get('/choice-events/{choiceEvent}', [ChoiceEventController::class,'show'])->name('choice-events.show');
        Route::get('/choice-events/{choiceEvent}/edit', [ChoiceEventController::class,'edit'])->name('choice-events.edit');
        Route::put('/choice-events/{choiceEvent}', [ChoiceEventController::class,'update'])->name('choice-events.update');
        Route::delete('/choice-events/{choiceEvent}', [ChoiceEventController::class,'destroy'])->name('choice-events.destroy');
        Route::delete('/choice-events/{choiceEvent}/posts/{post}/choices/{choice}', [ChoiceEventController::class,'destroyChoice'])->name('choice-options.destroy');
        Route::post('/choice-events/{choiceEvent}/posts', [ChoiceEventController::class,'storePost'])->name('choice-posts.store');
        Route::post('/choice-events/{choiceEvent}/posts/{post}/choices', [ChoiceEventController::class,'storeChoice'])->name('choice-options.store');
        Route::put('/choice-events/{choiceEvent}/posts/{post}/choices', [ChoiceEditorController::class,'save'])->name('choice-editor.save');
        Route::get('/choice-events/{choiceEvent}/posts/{post}/choice-sample', [ChoiceEditorController::class,'sample'])->name('choice-editor.sample');
        Route::post('/choice-events/{choiceEvent}/posts/{post}/choice-import/preview', [ChoiceEditorController::class,'preview'])->name('choice-editor.preview');
        Route::post('/choice-events/{choiceEvent}/posts/{post}/choice-import/confirm', [ChoiceEditorController::class,'confirm'])->name('choice-editor.confirm');
        Route::get('/candidate-import-template', [CandidateImportController::class,'template'])->name('choice-import.template');
        Route::get('/choice-events/{choiceEvent}/posts/{post}/candidates', [CandidateImportController::class,'index'])->name('choice-import.index');
        Route::delete('/choice-events/{choiceEvent}/posts/{post}/candidates', [CandidateImportController::class,'reset'])->name('choice-import.reset');
        Route::post('/choice-events/{choiceEvent}/posts/{post}/candidates/preview', [CandidateImportController::class,'preview'])->name('choice-import.preview');
        Route::post('/choice-events/{choiceEvent}/posts/{post}/candidates/confirm', [CandidateImportController::class,'confirm'])->name('choice-import.confirm');
    });
});
