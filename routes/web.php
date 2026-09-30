<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{UserController,ChoiceEventController,CandidateImportController};
use App\Http\Middleware\ChoiceStaff;
use App\Models\ChoiceEvent;
Route::get('/', fn()=>view('choice.public.home',['events'=>ChoiceEvent::available()->orderBy('end_at')->get()]))->name('home');
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
        Route::get('/candidate-import-template', [CandidateImportController::class,'template'])->name('choice-import.template');
        Route::get('/choice-events/{choiceEvent}/posts/{post}/candidates', [CandidateImportController::class,'index'])->name('choice-import.index');
        Route::post('/choice-events/{choiceEvent}/posts/{post}/candidates/preview', [CandidateImportController::class,'preview'])->name('choice-import.preview');
        Route::post('/choice-events/{choiceEvent}/posts/{post}/candidates/confirm', [CandidateImportController::class,'confirm'])->name('choice-import.confirm');
    });
});
