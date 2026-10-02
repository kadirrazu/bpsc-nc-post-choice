@extends('layouts.app')
@section('title','Candidates')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="mb-1">{{ $post->post_code }} — Candidates</h1><p class="text-secondary mb-0">{{ $event->title }}</p></div><a class="btn btn-outline-secondary" href="{{ route('choice-events.show',$event) }}">Back to Event</a></div>
<div class="row g-3 mb-4">
@foreach(['total'=>'Total Candidates','submitted'=>'Submitted','not_submitted'=>'Not Submitted','imports'=>'Import Batches'] as $key=>$label)
<div class="col-6 col-lg-3"><div class="card card-body"><div class="text-secondary">{{ $label }}</div><div class="h1 mb-0 mt-2">{{ number_format($summary[$key]) }}</div></div></div>
@endforeach
</div>
@if($summary['last_import'])<p class="text-secondary small">Last import: {{ \Illuminate\Support\Carbon::parse($summary['last_import'])->format('d M Y, h:i A') }} (Bangladesh time)</p>@endif
@if(in_array($event->status,['DRAFT','PUBLISHED'],true) && $event->lifecycle!=='ARCHIVED' && !$event->multiple_posts)
<details class="card card-body mb-4" @if($summary['total']===0) open @endif><summary class="h3 mb-0">Import Candidates</summary><form class="mt-3" method="post" enctype="multipart/form-data" action="{{ route('choice-import.preview',[$event,$post]) }}">@csrf
<p class="text-secondary">CSV / XLS / XLSX · Maximum 5 MB and 10,000 rows. Excel uses the first worksheet. Replace the sample row before uploading.</p>
<p class="text-secondary">Required: user, reg, name, b_date. All other fields, including fname and mname, are optional. Keep identifiers as Text to preserve leading zeros; user/reg allow up to 10 characters.</p>
<p class="text-secondary">Birth date: DDMMYYYY or DDMMYY. Years 00–{{ config('choice.birth_year_pivot') }} use 20YY; remaining years use 19YY.</p>
<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-primary" href="{{ route('choice-import.template',['format'=>'xlsx','post_id'=>$post->id]) }}">Download Sample Excel (XLSX)</a><a class="btn btn-outline-secondary" href="{{ route('choice-import.template',['post_id'=>$post->id]) }}">Download CSV Template</a></div>
<label class="form-label" for="file">Candidate file (CSV / XLS / XLSX)</label><input id="file" class="form-control mb-3" type="file" name="file" accept=".csv,.xls,.xlsx" required><button class="btn btn-primary">Validate and Preview</button></form></details>
@elseif($event->multiple_posts)<div class="alert alert-info">Multiple-post identity matching and import are scheduled for Phase 2.</div>@else<div class="alert alert-info">Import is unavailable for archived or cancelled events.</div>@endif
<div class="card mb-4"><div class="card-body"><form method="get" action="{{ route('choice-import.index',[$event,$post]) }}"><div class="row g-3">
<div class="col-md-6 col-xl-4"><label class="form-label" for="search">Search candidates</label><input class="form-control" id="search" name="search" value="{{ $search }}" placeholder="User ID, registration, name or parent name" maxlength="255"></div>
<div class="col-md-6 col-xl-2"><label class="form-label" for="district">District</label><select id="district" name="district" class="form-select"><option value="">All districts</option>@foreach($districts as $district)<option value="{{ $district }}" @selected(($filters['district'] ?? '')===$district)>{{ $district }}</option>@endforeach</select></div>
<div class="col-md-4 col-xl-2"><label class="form-label" for="submission_status">Submission</label><select id="submission_status" name="submission_status" class="form-select"><option value="">All candidates</option><option value="submitted" @selected(($filters['submission_status'] ?? '')==='submitted')>Submitted</option><option value="not_submitted" @selected(($filters['submission_status'] ?? '')==='not_submitted')>Not submitted</option></select></div>
<div class="col-md-4 col-xl-2"><label class="form-label" for="parent_names">Parent names</label><select id="parent_names" name="parent_names" class="form-select"><option value="">All</option><option value="complete" @selected(($filters['parent_names'] ?? '')==='complete')>Both present</option><option value="missing" @selected(($filters['parent_names'] ?? '')==='missing')>One or both missing</option></select></div>
<div class="col-md-4 col-xl-2"><label class="form-label" for="per_page">Rows per page</label><select id="per_page" name="per_page" class="form-select">@foreach([25,50,100] as $size)<option value="{{ $size }}" @selected($perPage===$size)>{{ $size }}</option>@endforeach</select></div>
</div><div class="d-flex gap-2 mt-3"><button class="btn btn-primary">Search / Filter</button><a class="btn btn-outline-secondary" href="{{ route('choice-import.index',[$event,$post]) }}">Clear Filters</a></div></form></div></div>
<div class="card"><div class="card-header"><h2 class="card-title">Candidate Records <span class="text-secondary">({{ number_format($applications->total()) }} matching)</span></h2></div><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Serial</th><th>User ID</th><th>Registration</th><th>Name</th><th>Birth date</th><th>District</th><th>Submission</th></tr></thead><tbody>
@forelse($applications as $app)<tr><td>{{ $applications->firstItem()+$loop->index }}</td><td class="text-nowrap">{{ $app->user }}</td><td class="text-nowrap">{{ $app->reg }}</td><td>{{ $app->candidate->name }}</td><td class="text-nowrap">{{ $app->candidate->b_date->format('d-m-Y') }}</td><td>{{ $app->dist_name ?: '—' }}</td><td><span class="badge {{ $app->submitted_count ? 'bg-green-lt' : 'bg-secondary-lt' }}">{{ $app->submitted_count ? 'Submitted' : 'Not submitted' }}</span></td></tr>@empty<tr><td colspan="7" class="text-secondary text-center py-4">{{ $summary['total'] ? 'No candidates match your search or filters.' : 'No candidates imported yet.' }}</td></tr>@endforelse
</tbody></table></div><div class="card-footer">@include('choice.import.pagination',['paginator'=>$applications])</div></div>
@if(auth()->user()->role===\App\Enums\UserRole::Admin)
<details class="card card-body mt-4"><summary class="text-danger">Delete Full Candidate Dataset / Import Again</summary>
@if($event->status==='DRAFT' && $event->lifecycle!=='ARCHIVED')
<p class="mt-3">This permanently deletes all candidate applications and import batches for this post, including associated candidate submissions. The event, post and choice options remain. Candidates shared with another post are preserved. Type DELETE to confirm.</p>
<form method="post" action="{{ route('choice-import.reset',[$event,$post]) }}">@csrf @method('DELETE')<label class="form-label" for="dataset-confirmation">Confirmation</label><input id="dataset-confirmation" name="confirmation" class="form-control mb-3" placeholder="DELETE" autocomplete="off" required><button class="btn btn-danger">Delete Full Dataset</button></form>
@else<p class="mt-3 mb-0">Dataset reset requires a current Draft event. Change the event to Draft before resetting.</p>@endif
</details>@endif
<a class="btn btn-outline-secondary mt-4" href="{{ route('choice-events.show',$event) }}">Back to Event</a>
@endsection
