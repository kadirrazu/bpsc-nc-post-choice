@extends('layouts.app')
@section('title','My Profile')
@section('content')
<h1 class="mb-3">My Profile</h1>
<form class="card card-body" method="post" action="{{ route('staff-profile.update') }}">@csrf @method('PUT')
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="{{ old('name',$user->name) }}" maxlength="255" required></div><div class="col-md-6"><label class="form-label" for="email">Email (Sign In)</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email',$user->email) }}" maxlength="255" required></div><div class="col-md-6"><label class="form-label" for="designation_id">Designation</label><select class="form-select" id="designation_id" name="designation_id" required><option value="">Select designation</option>@foreach($designations as $designation)<option value="{{ $designation->id }}" @selected(old('designation_id',$user->designation_id)==$designation->id)>{{ $designation->name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Role</label><div class="form-control-plaintext">{{ $user->role->label() }}</div></div></div>
<div class="mt-3"><button class="btn btn-primary">Save Profile</button> <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Back</a></div></form>
@endsection
