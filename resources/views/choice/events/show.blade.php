@extends('layouts.app')
@section('title',$event->title)
@section('content')
<div class="d-flex justify-content-between mb-3"><div><h1>{{ $event->title }}</h1><span class="badge bg-blue-lt">{{ $event->lifecycle }}</span></div>@if($event->lifecycle!=='ARCHIVED')<a class="btn btn-outline-primary align-self-start" href="{{ route('choice-events.edit',$event) }}">Edit configuration</a>@endif</div>
<p class="text-secondary">Post code: {{ $event->post_code ?? '—' }} · {{ $event->unit ?? 'Unit not set' }}</p>
<p class="text-secondary">{{ $event->start_at->format('d M Y, h:i A') }} — {{ $event->end_at->format('d M Y, h:i A') }} · Bangladesh time</p>
@if($event->lifecycle==='ARCHIVED')<div class="alert alert-info">This event is archived. Data remains available for review. Exports are available below.</div>@endif
@if($event->status==='CLOSED')<div class="alert alert-warning">This event is closed. New choice submissions are disabled. Existing submissions and exports remain available.</div>@endif
@if(auth()->user()->role===\App\Enums\UserRole::Admin && $event->status==='PUBLISHED' && $event->end_at->gte(now()))<a class="btn btn-outline-danger mb-3" href="{{ route('choice-events.confirm-close',$event) }}">Mark as Closed</a>@endif
<div class="card card-body mb-4"><h2 class="h3">Submissions & Administrative Records</h2>@if($event->multiple_posts)@include('choice.events.multiple-statistics',['showExports'=>true])<div class="mb-3"></div>@endif<div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('choice-submissions.index',$event) }}">Manage Submissions</a><a class="btn btn-outline-primary" href="{{ route('choice-exports.record',[$event,'xlsx']) }}">Event Record — Excel</a><a class="btn btn-outline-primary" href="{{ route('choice-exports.record',[$event,'pdf']) }}">Event Record — PDF</a></div><p class="text-secondary mt-2 mb-0">Download candidate choices (XLSX / DBF) from Manage Submissions.</p></div>
@if($event->status==='DRAFT' && $event->lifecycle!=='ARCHIVED' && ($event->multiple_posts || $event->posts->isEmpty()))<form class="card card-body mb-4" method="post" action="{{ route('choice-posts.store',$event) }}">@csrf<h2 class="h3">Add post</h2><div class="row g-3">@foreach(['post_code'=>'Post code','title'=>'Post title','organization'=>'Organization','ministry'=>'Ministry'] as $key=>$label)<div class="col-md-6"><label class="form-label" for="post_{{ $key }}">{{ $label }}</label><input id="post_{{ $key }}" class="form-control" name="{{ $key }}" value="{{ old($key, $key==='post_code' ? $event->post_code : ($key==='title' ? $event->title : null)) }}" @required(in_array($key,['post_code','title']))></div>@endforeach</div><div class="mt-3"><button class="btn btn-primary">Add post</button></div></form>@endif
<div class="alert alert-info">Candidate import: use <strong>Import / View Candidates</strong> below. @if($event->multiple_posts)Upload one candidate file per applied post, then review the matching results before confirming import.@else Single-post CSV import is available for current Draft and Published events.@endif</div>
@foreach($event->posts as $post)<section class="card mb-4"><div class="card-header d-flex justify-content-between"><h2 class="card-title">{{ $post->post_code }} — {{ $post->title }}</h2><a class="btn btn-primary btn-sm" href="{{ route('choice-import.index',[$event,$post]) }}">Import / View Candidates ({{ $post->applications_count }})</a></div><div class="card-body"><p class="text-secondary">{{ $post->organization }} {{ $post->ministry ? ' · '.$post->ministry : '' }}</p>@include('choice.options.editor')
</div></section>@endforeach
@if(auth()->user()->role===\App\Enums\UserRole::Admin)
<details class="card card-body mb-4"><summary class="text-danger">Delete event and all its data</summary><p class="mt-3">This permanently deletes this event, posts, choices, candidates, imports, submissions and event audit records. Type DELETE to confirm.</p><form method="post" action="{{ route('choice-events.destroy',$event) }}">@csrf @method('DELETE')<label class="form-label" for="delete-confirmation">Confirmation</label><input id="delete-confirmation" class="form-control mb-3" name="confirmation" autocomplete="off" placeholder="DELETE" required><button class="btn btn-danger">Delete event permanently</button></form></details>
@endif
<a href="{{ route('choice-events.index') }}" class="btn btn-outline-secondary">Back to events</a>
@endsection

@push('scripts')
@vite('resources/js/choice-editor.js')
@endpush
