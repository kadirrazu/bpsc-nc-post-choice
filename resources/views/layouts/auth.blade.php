<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', 'Authentication') | {{ config('app.name') }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('choice.public.shell-styles')
    @include('choice.shared.ui-head')

    @stack('styles')
</head>

<body class="choice-public choice-home auth-shell">
    <div class="public-frame">
        <header class="choice-header">
@include('choice.shared.brand-row')
<div class="container-xl choice-header-actions d-print-none">
<a class="btn btn-outline-secondary btn-sm" href="{{ route('home') }}"><x-icon name="world"/>Public Page</a>
</div></header>
        <main class="auth-main">
        <div class="container container-tight py-4">
            @yield('content')
        </div>
        </main>
        <footer class="public-footer"><div class="container-xl public-footer-content"><span>Bangladesh Public Service Commission (BPSC)</span><span class="public-footer-credit">Software Developed By: <strong>IT Section, BPSC</strong></span></div></footer>
    </div>

    @stack('scripts')
</body>
</html>
