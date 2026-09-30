@extends('layouts.app')
@section('title','Dashboard')
@section('content')
<h1>BPSC Choice Taking System</h1><p class="text-secondary">Manage choice events, configure posts and choices, and import eligible candidates.</p>
@if(auth()->user()->is_active && in_array(auth()->user()->role,[\App\Enums\UserRole::Admin,\App\Enums\UserRole::Operator],true))
<div class="row g-3"><div class="col-md-6"><div class="card card-body"><h2 class="h3">Choice events</h2><p>Create an event, add its posts and choices, then import candidates.</p><a class="btn btn-primary align-self-start" href="{{ route('choice-events.index') }}">Manage events</a></div></div><div class="col-md-6"><div class="card card-body"><h2 class="h3">Archive</h2><p>Review completed events and their candidate records.</p><a class="btn btn-outline-primary align-self-start" href="{{ route('choice-events.index',['archive'=>1]) }}">View archive</a></div></div></div>
@endif
@endsection
