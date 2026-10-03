@extends('layouts.app')
@section('title','Change Password')
@section('content')
<h1 class="mb-3">Change Password</h1>
<form class="card card-body" method="post" action="{{ route('staff-profile.update-password') }}" style="max-width:600px">@csrf @method('PUT')
<label class="form-label" for="current_password">Current Password</label><input class="form-control mb-3" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
<label class="form-label" for="password">New Password</label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required><p class="form-hint mb-3">Use at least 8 characters.</p>
<label class="form-label" for="password_confirmation">Confirm New Password</label><input class="form-control mb-3" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
<div><button class="btn btn-primary">Update Password</button> <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Back</a></div></form>
@endsection
