@extends('layouts.app')
@section('title','Edit Post')
@section('content')
<h1 class="mb-3">Edit Post</h1>
<form class="card card-body" method="post" action="{{ route('choice-posts.update',[$event,$post]) }}">@csrf @method('PUT')
<h2 class="h3">{{ $event->title }}</h2><p class="text-secondary">Update this applied post's details. Existing candidate links and choices are preserved.</p>
<input type="hidden" name="revision" value="{{ $revision }}">
<div class="row g-3">@foreach(['post_code'=>'Post Code','title'=>'Post Title','organization'=>'Organization','ministry'=>'Ministry'] as $key=>$label)
<div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ $label }}</label><input class="form-control" id="{{ $key }}" name="{{ $key }}" value="{{ old($key,$post->$key) }}" maxlength="{{ $key==='post_code' ? 20 : 255 }}" @required(in_array($key,['post_code','title']))></div>
@endforeach</div><div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary">Save Post</button><a class="btn btn-outline-secondary" href="{{ route('choice-events.show',$event) }}">Back to Event</a></div>
</form>
@endsection
