@extends('layouts.app')
@section('title','Close Choice Event')
@section('content')
<div class="card card-body mx-auto" style="max-width:720px">
<h1 class="h2">Mark Event as Closed</h1>
<p>{{ $event->title }} · {{ $event->post_code }} · {{ $event->unit }}</p>
<p>Closing this event immediately stops new choice submissions, including candidates who have already signed in or reviewed their choices. The scheduled closing time will remain unchanged. Existing submissions and administrative exports remain available.</p>
<form method="post" action="{{ route('choice-events.close',$event) }}">@csrf
<label class="form-check mb-3"><input class="form-check-input" type="checkbox" required name="confirmation" value="CLOSE"><span class="form-check-label">I confirm closing this event and stopping new submissions.</span></label>
<button class="btn btn-danger">Confirm Close</button>
<a class="btn btn-outline-secondary" href="{{ route('choice-events.show',$event) }}">Go Back</a>
</form></div>
@endsection
