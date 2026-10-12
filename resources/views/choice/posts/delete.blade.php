@extends('layouts.app')
@section('title','Delete Post')
@section('content')
<h1 class="mb-3">Delete Post</h1>
<div class="card card-body"><h2 class="h3">{{ $post->post_code }} — {{ $post->title }}</h2><p class="text-secondary">{{ $event->title }}</p>
<div class="alert alert-danger">This permanently deletes this post, its choices and its imported candidate dataset. Other applied posts remain available.</div>
<div class="table-responsive mb-3"><table class="table table-vcenter"><tbody>
<tr><th>Applications to delete</th><td>{{ number_format($applications) }}</td></tr><tr><th>Candidates only in this post — delete</th><td>{{ number_format($exclusive) }}</td></tr><tr><th>Candidates also linked to other posts — preserve</th><td>{{ number_format($shared) }}</td></tr><tr><th>Choices to delete</th><td>{{ number_format($choices) }}</td></tr>
</tbody></table></div><p>Type <strong>DELETE</strong> to confirm. This action cannot be undone.</p>
<form method="post" action="{{ route('choice-posts.destroy',[$event,$post]) }}">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $revision }}"><label class="form-label" for="confirmation">Confirmation</label><input class="form-control mb-3" id="confirmation" name="confirmation" autocomplete="off" required placeholder="DELETE"><div class="d-flex flex-wrap gap-2"><button class="btn btn-danger">Delete Post and Dataset</button><a class="btn btn-outline-secondary" href="{{ route('choice-events.show',$event) }}">Cancel</a></div></form>
</div>
@endsection
