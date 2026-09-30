<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BPSC | Choice Taking System</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="choice-public">
<header class="choice-header"><div class="container-fluid px-3 px-md-4 d-flex align-items-center justify-content-between gap-3"><a href="{{ route('home') }}" class="choice-brand"><span>Bangladesh Public Service Commission (BPSC)<small>Choice Taking System</small></span></a><a class="btn btn-outline-secondary btn-sm flex-shrink-0" href="{{ route('login') }}">Administrator Sign In</a></div></header>
<main class="container-xl py-4"><h1 class="h2 mb-2">Choice Submission</h1><p class="text-secondary mb-4">Select an available event to submit your post preferences. Keep your User ID and Registration Number ready.</p>
<div class="card"><div class="card-body"><h2 class="h3">Available events</h2>@forelse($events as $event)<article class="border-top pt-3 mt-3"><h3 class="h3 mb-2">{{ $event->title }}</h3><p class="small text-secondary mb-2">Post code: {{ $event->post_code }}{{ $event->unit ? ' · '.$event->unit : '' }} · Closes {{ $event->end_at->format('d M Y, h:i A') }}</p>@if($event->instructions)<p class="choice-instructions mb-2">{{ $event->instructions }}</p>@endif<p class="small text-secondary mb-0">Candidate submission will be available soon.</p></article>@empty<p class="text-secondary mb-0">No choice events are currently available. Please check again during the announced submission period.</p>@endforelse</div></div>
<p class="small text-secondary mt-3">All dates and times are in Bangladesh time (UTC+06:00).</p></main>
<footer class="container-xl pb-3 small text-secondary">Bangladesh Public Service Commission (BPSC)</footer>
</body></html>
