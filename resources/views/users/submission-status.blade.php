@extends('layouts.app')
@section('title','Submission Status')
@section('content')
<h1>Submission Status</h1><p class="text-secondary">Select an event to view candidate submission status. This is a read-only page.</p>
<form class="card card-body mb-3" method="get"><label class="form-label" for="q">Search event title or post code</label><div class="d-flex gap-2"><input class="form-control" id="q" name="q" value="{{ request('q') }}" maxlength="255"><button class="btn btn-primary">Search</button></div></form>
<div class="card"><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>#</th><th>Event</th><th>Post Code / Unit</th><th>Status</th><th>Candidates</th><th></th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $events->firstItem()+$loop->index }}</td><td>{{ $event->title }}</td><td>{{ $event->post_code }}<br><small>{{ $event->unit }}</small></td><td>{{ $event->lifecycle }}</td><td>{{ $event->candidates_count }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('choice-submissions.index',$event) }}">View Submissions</a></td></tr>@empty<tr><td colspan="6" class="text-center text-secondary py-4">No events found.</td></tr>@endforelse</tbody></table></div><div class="card-footer">@include('choice.import.pagination',['paginator'=>$events])</div></div>
@endsection
