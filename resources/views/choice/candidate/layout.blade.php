<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>BPSC | Choice Submission</title>
@vite(['resources/css/app.css','resources/js/app.js'])
@include('choice.public.shell-styles')
@include('choice.shared.fonts')
</head>
<body class="choice-public choice-home">
<div class="container-xl public-frame">
<header class="choice-header"><div class="public-header-content">
<a class="choice-brand" href="{{ route('home') }}">Bangladesh Public Service Commission (BPSC)<small>Choice Taking System</small></a>
<div class="d-flex flex-wrap align-items-center gap-2 d-print-none">
<a class="btn btn-outline-secondary" href="{{ route('home') }}"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M5 12l6 6M5 12l6-6"/></svg>Back to Events</a>
@php($candidateAccess=session('candidate_access.'.$event->id))
@if(is_array($candidateAccess) && isset($candidateAccess['expires']) && $candidateAccess['expires']>=now()->timestamp)
<form class="m-0" method="post" action="{{ route('candidate.logout',$event) }}">@csrf<button class="btn btn-danger"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M8 12h10"/></svg>Candidate Sign Out</button></form>
@endif
</div></div></header>
<main class="public-main">
<div class="public-intro-block"><h1 class="h2 candidate-event-title mb-1">{{ $event->title }}</h1>
<p class="text-secondary small mb-1">Post code: {{ $event->post_code }}{{ $event->unit ? ' · '.$event->unit : '' }}</p>
<p class="public-time-hint">All dates and times are in Bangladesh time (UTC+06:00).</p></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
<footer class="public-footer"><span>Bangladesh Public Service Commission (BPSC)</span><span class="public-footer-credit">Software Developed By: <strong>IT Section, BPSC</strong></span></footer>
</div>
@stack('scripts')
</body>
</html>
