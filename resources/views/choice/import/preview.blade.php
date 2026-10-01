@extends('layouts.app')
@section('title','Import Preview')
@section('content')
<h1>Import preview</h1><p>{{ $event->title }} · {{ $post->title }} · {{ count($rows) }} candidate rows</p>
@if($importErrors)<div class="alert alert-danger"><strong>Fix the file and upload again. No candidates have been imported.</strong><ul class="mt-2">@foreach(array_slice($importErrors,0,100) as $error)<li>{{ $error }}</li>@endforeach</ul>@if(count($importErrors)>100)<p>{{ count($importErrors) }} errors found; first 100 shown.</p>@endif</div>@else<div class="alert alert-success">Validation passed. Review the first {{ min(count($rows),20) }} rows below, then confirm all {{ count($rows) }} rows.</div>@endif
<div class="card table-responsive"><table class="table"><thead><tr><th>User ID</th><th>Registration</th><th>Name</th><th>Father</th><th>Mother</th><th>Birth date (normalized)</th></tr></thead><tbody>@foreach(array_slice($rows,0,20) as $row)<tr>@foreach(['user','reg','name','fname','mname','b_date'] as $key)<td>{{ $row[$key] ?? '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div>
@if(!$importErrors)<form method="post" class="mt-3" action="{{ route('choice-import.confirm',[$event,$post]) }}">@csrf<input type="hidden" name="nonce" value="{{ $nonce }}"><button class="btn btn-primary">Confirm import ({{ count($rows) }} candidates)</button></form>@endif
<a class="btn btn-outline-secondary mt-3" href="{{ route('choice-import.index',[$event,$post]) }}">Back to upload</a>
@endsection
