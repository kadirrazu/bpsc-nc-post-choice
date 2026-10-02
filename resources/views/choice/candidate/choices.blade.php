@extends('choice.candidate.layout')
@section('content')
<section class="card mb-4" aria-labelledby="candidate-information"><div class="card-body"><h2 id="candidate-information" class="h3 mb-3">Candidate Information</h2><dl class="candidate-info mb-0">
@foreach(['User ID'=>$application?->user,'Registration'=>$application?->reg,'Name'=>$person->name,"Father's Name"=>$person->fname,"Mother's Name"=>$person->mname,'Birth Date'=>$person->b_date->format('d-m-Y'),'District'=>$application?->dist_name] as $label=>$value)
<div><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>@endforeach
</dl></div></section>
<div class="public-intro-block"><h2 class="h2 mb-2">Arrange your preferred choices</h2><p class="text-secondary mb-4">Click or drag choices between panels. Arrange selected choices in your preferred order, then review before submitting.</p></div>
@if($options->isEmpty())<div class="alert alert-info">No applicable choices are configured. Please contact the administrator.</div>@else
@php($choiceData=$options->map(fn($option)=>['id'=>(string)$option->id,'code'=>$option->code,'title'=>$option->title,'posts'=>$option->post_count])->values())
<form id="candidate-choice-form" method="post" action="{{ route('candidate.review',$event) }}">@csrf
<script type="application/json" id="candidate-choice-data">@json(['options'=>$choiceData,'selected'=>$selectedIds])</script>
<div class="row g-4 choice-workspace">
<section class="col-lg-6"><div class="card choice-panel h-100"><div class="card-header d-flex justify-content-between gap-2"><div><h3 class="card-title mb-1">Available Choices <span class="badge bg-secondary-lt" id="available-count">{{ $options->count() }}</span></h3><div class="text-secondary small">Original order · Click to add</div></div><button type="button" class="btn btn-sm btn-outline-primary" id="add-all">Add All →</button></div><div class="card-body choice-drop-zone" id="available-panel" data-panel="available"><ol id="available-choices" class="choice-panel-list" aria-label="Available choices"></ol><p class="choice-panel-empty" id="available-empty" hidden>All applicable choices have been selected. Remove a choice to return it here.</p></div></div></section>
<section class="col-lg-6"><div class="card choice-panel choice-selected-panel h-100"><div class="card-header d-flex justify-content-between gap-2"><div><h3 class="card-title mb-1">Selected Choices <span class="badge bg-blue-lt" id="selected-count">0</span></h3><div class="text-secondary small">Preference order · Drag or use ↑ / ↓</div></div><button type="button" class="btn btn-sm btn-outline-secondary" id="remove-all">← Remove All</button></div><div class="card-body choice-drop-zone" id="selected-panel" data-panel="selected"><ol id="selected-choices" class="choice-panel-list" aria-label="Selected choices in preference order"></ol><p class="choice-panel-empty" id="selected-empty">Click a choice on the left or drag it here to begin.</p></div></div></section>
</div>
<div id="choice-inputs"></div><div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4"><p id="selection-status" class="text-secondary mb-0" role="status" aria-live="polite">Select at least one choice.</p><button class="btn btn-primary" id="review-choices">Review Choices →</button></div>
<p class="small text-secondary mt-3">Preference 1 is your highest choice. You may leave choices unselected. Final submission is allowed once.</p>
<noscript><div class="alert alert-warning">Enable JavaScript to select and reorder choices.</div></noscript>
</form>@endif
@endsection
@push('scripts')
@vite(['resources/css/candidate-choices.css','resources/js/candidate-choices.js'])
@endpush
