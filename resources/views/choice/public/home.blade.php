<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>BPSC | Choice Taking System</title>
@vite(['resources/css/app.css','resources/js/app.js'])
@include('choice.public.shell-styles')
</head>
<body class="choice-public choice-home">
<div class="container-xl public-frame">
<header class="choice-header"><div class="public-header-content">
<a href="{{ route('home') }}" class="choice-brand">Bangladesh Public Service Commission (BPSC)<small>Choice Taking System</small></a>
<a class="btn btn-outline-secondary btn-sm" href="{{ route('login') }}">Administrator Sign In</a>
</div></header>
<main class="public-main">
<div class="public-intro-block"><h1 class="h2 mb-1">Choice Submission</h1>
<p class="public-time-hint">All dates and times are in Bangladesh time (UTC+06:00).</p>
<p class="text-secondary public-intro mb-4">Select an available event to submit your post preferences. Keep your User ID and birth date (DDMMYYYY) ready.</p></div>
<section class="card public-events" aria-labelledby="available-events-heading"><div class="card-body">
<h2 id="available-events-heading" class="h3 mb-0">Available events</h2>
@forelse($events as $event)
<article class="public-event"><div class="public-event-heading"><div>
<h3 class="public-event-title">{{ $event->title }}</h3>
<dl class="public-event-meta"><div><dt>Post code</dt><dd>{{ $event->post_code }}</dd></div>@if($event->unit)<div><dt>Unit</dt><dd>{{ $event->unit }}</dd></div>@endif<div><dt>Submission closes</dt><dd>{{ $event->end_at->format('d M Y, h:i A') }}</dd></div></dl>
@if($event->instructions)<p class="choice-instructions mb-0">{{ $event->instructions }}</p>@endif
</div><a class="btn btn-primary public-submit" href="{{ route('candidate.login',$event) }}">Submit Choices</a></div></article>
@empty
<div class="public-empty" role="status"><strong>No events are accepting choices right now.</strong><p>Please check again during the announced submission period.</p></div>
@endforelse
</div></section>
</main>
<footer class="public-footer"><span>Bangladesh Public Service Commission (BPSC)</span><span class="public-footer-credit">Software Developed By: <strong>IT Section, BPSC</strong></span></footer>
</div>
</body>
</html>
