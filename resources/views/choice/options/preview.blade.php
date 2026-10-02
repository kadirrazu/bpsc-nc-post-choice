@extends('layouts.app')
@section('title','Choice Import Preview')
@section('content')
<h1>Choice Import Preview</h1><p>{{ $event->title }} · {{ $post->title }}</p>
<div class="alert alert-success">{{ count($rows) }} choices validated. No choices have been imported yet. Confirm to append them to this post.</div>
<div class="card table-responsive"><table class="table"><thead><tr><th>Import order</th><th>Code</th><th>Choice title</th><th>Post count</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ $row['sort_order'] }}</td><td>{{ $row['code'] }}</td><td><span data-choice-title class="{{ preg_match('/[\x{0980}-\x{09FF}]/u',$row['title']) ? 'choice-title-bn' : '' }}">{{ $row['title'] }}</span></td><td>{{ $row['post_count'] ?? '—' }}</td></tr>@endforeach</tbody></table></div>
<form method="post" class="mt-3" action="{{ route('choice-editor.confirm',[$event,$post]) }}">@csrf<input type="hidden" name="nonce" value="{{ $nonce }}"><button class="btn btn-primary">Confirm Import ({{ count($rows) }} choices)</button> <a class="btn btn-outline-secondary" href="{{ route('choice-events.show',$event) }}">Cancel / Back</a></form>
@endsection
