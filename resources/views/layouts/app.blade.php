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
        @yield('title', 'Dashboard') | {{ config('app.name') }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('choice.public.shell-styles')
@include('choice.shared.ui-head')
    <style>
    .backend-shell .choice-header { box-shadow:none; }
    .backend-shell .backend-main { display:flex; flex:1; flex-direction:column; min-width:0; }
    .backend-shell .backend-main > .page-body { flex:1; padding:28px 0; margin:0; }
    .backend-shell .backend-content { min-width:0; }
    .backend-footer-main,.backend-footer-details { display:flex; width:100%; flex-wrap:wrap; justify-content:space-between; gap:8px 24px; }
    .backend-footer-details { font-size:11px; color:#64748b; }
    @media(max-width:575px) { .backend-shell .backend-main > .page-body { padding:22px 0; } }
    </style>
    @stack('styles')
@include('choice.shared.fonts')
</head>

<body class="choice-public backend-shell">
    <div class="public-frame">
        <header class="choice-header d-print-none">
            @include('choice.shared.brand-row',['brandRoute'=>'dashboard'])
            <nav class="navbar navbar-expand-md choice-menu-row" aria-label="Main navigation">
                <div class="container-xl choice-menu-container">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                    <div class="collapse navbar-collapse" id="navbar-menu">
                        @php
                            $menuEvent=request()->route('choiceEvent');
                            $choiceSection=request()->routeIs('choice-events.*','choice-submissions.*','choice-import.*','choice-editor.*','choice-options.*','choice-posts.*','choice-exports.*','choice-data.*','choice-multiple.*');
                            $archiveSection=request()->routeIs('choice-events.index') ? request()->boolean('archive') : ($menuEvent instanceof \App\Models\ChoiceEvent && in_array($menuEvent->lifecycle,['ARCHIVED','CANCELLED'],true));
                        @endphp
                        <ul class="navbar-nav">
                            <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><a class="nav-link" href="{{ route('dashboard') }}"><x-icon name="dashboard"/>Dashboard</a></li>
                            @if(auth()->user()->role===\App\Enums\UserRole::Admin)
                            <li class="nav-item {{ $choiceSection && !$archiveSection ? 'active' : '' }}"><a class="nav-link" href="{{ route('choice-events.index') }}"><x-icon name="choices"/>Choice Events</a></li>
                            <li class="nav-item {{ $choiceSection && $archiveSection ? 'active' : '' }}"><a class="nav-link" href="{{ route('choice-events.index', ['archive'=>1]) }}"><x-icon name="archive"/>Archive</a></li>
                            <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}"><a class="nav-link" href="{{ route('users.index') }}"><x-icon name="users"/>Users</a></li>
                            @else
                            <li class="nav-item {{ request()->routeIs('submission-status.*','choice-submissions.index') ? 'active' : '' }}"><a class="nav-link" href="{{ route('submission-status.index') }}"><x-icon name="choices"/>Submission Status</a></li>
                            @endif
                            <li class="nav-item"><a class="nav-link" href="{{ route('home') }}"><x-icon name="world"/>Public page</a></li>
                        </ul>
                    </div>
                    <div class="choice-account-actions">
                        <div class="dropdown">
                            <a href="#" class="choice-account-trigger" data-bs-toggle="dropdown" aria-label="Open user menu" aria-expanded="false">
                                <span class="avatar avatar-sm">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
                                <span class="choice-account-label"><span class="choice-account-name">{{ auth()->user()->name }}</span><span class="choice-account-designation">{{ auth()->user()->designation?->name ?? 'Designation not assigned' }}</span></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <div class="px-3 py-2"><strong>{{ auth()->user()->name }}</strong><div class="text-secondary small">{{ auth()->user()->designation?->name ?? 'Designation not assigned' }}</div></div>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('staff-profile.edit') }}"><x-icon name="user"/>My Profile</a>
                                <a class="dropdown-item" href="{{ route('staff-profile.password') }}"><x-icon name="key"/>Change Password</a>
                            </div>
                        </div>
                        <form class="m-0" method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-danger btn-sm" type="submit"><x-icon name="logout"/>Sign Out</button></form>
                    </div>
                </div>
            </nav>
        </header>

        <div class="backend-main">
            @hasSection('page-header')
                <div class="page-header d-print-none">
                    <div class="container-xl backend-content">
                        @yield('page-header')
                    </div>
                </div>
            @endif

            <div class="page-body">
                <div class="container-xl backend-content">
                    @if (session('success'))
                        <div
                            class="alert alert-success alert-dismissible"
                            role="alert"
                        >
                            {{ session('success') }}

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"
                            ></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    @yield('content')
                </div>
            </div>

            <footer class="public-footer d-print-none"><div class="container-xl public-footer-content">
                <div class="backend-footer-main"><span>Bangladesh Public Service Commission (BPSC)</span><span class="public-footer-credit">Software Developed By: <strong>IT Section, BPSC</strong></span></div>
                <div class="backend-footer-details"><span>Software Version: 1.0</span><span>Developer: Md. Abdul Kadir [Programmer]</span></div>
            </div></footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>