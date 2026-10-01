<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BPSC | Choice Submission</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body class="choice-public">
<header class="choice-header"><div class="container-fluid px-4 d-flex flex-wrap justify-content-between gap-3"><a class="choice-brand" href="{{ route('home') }}">Bangladesh Public Service Commission (BPSC)<small>Choice Taking System</small></a><a class="btn btn-outline-secondary btn-sm" href="{{ route('home') }}">Back to Events</a></div></header>
<main class="container-xl py-4"><h1 class="h2">{{ $event->title }}</h1><p class="text-secondary">{{ $event->post_code }} · {{ $event->unit }} · Bangladesh time (UTC+06:00)</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
@if(session('candidate_access.'.$event->id))<form class="mt-4 d-print-none" method="post" action="{{ route('candidate.logout',$event) }}">@csrf<button class="btn btn-outline-secondary">Candidate Sign Out</button></form>@endif
</main>@stack('scripts')</body></html>
