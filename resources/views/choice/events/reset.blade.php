@extends('layouts.app')
@section('title','Reset All Choice Data')
@section('content')
<div class="card"><div class="card-body">
<h1 class="h2">Reset All Choice Data</h1>
<div class="alert alert-danger">This permanently deletes all choice events, posts, choices, candidates, imports, submissions and choice audit records. Download any required exports first. Staff accounts will be retained.</div>
<table class="table"><thead><tr><th>Data</th><th class="text-end">Records</th></tr></thead><tbody>@foreach($counts as $table=>$count)<tr><td>{{ ucwords(str_replace('_',' ',$table)) }}</td><td class="text-end">{{ number_format($count) }}</td></tr>@endforeach</tbody></table>
<form method="post" action="{{ route('choice-data.destroy') }}">@csrf @method('DELETE')
<label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="acknowledge" value="1" required><span class="form-check-label">I understand that all choice data will be permanently deleted.</span></label>
<label class="form-label" for="confirmation">Type RESET ALL to confirm</label><input id="confirmation" class="form-control mb-3" name="confirmation" autocomplete="off" required placeholder="RESET ALL" style="max-width:320px">
<div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('choice-events.index') }}">Back to Events</a><button class="btn btn-danger">Delete All Choice Data</button></div>
</form></div></div>
@endsection
