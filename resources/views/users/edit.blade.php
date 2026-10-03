@extends('layouts.app')
@section('title','Edit User')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1>Edit User</h1><a class="btn btn-outline-secondary align-self-start" href="{{ route('users.index') }}">Back to Users</a></div>
<form class="card" method="post" action="{{ route('users.update',$user) }}">@csrf @method('PUT')<div class="card-body">@include('users._form')</div><div class="card-footer text-end"><button class="btn btn-primary">Save Changes</button></div></form>
@endsection
