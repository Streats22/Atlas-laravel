<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">✉</span> {{ config('app.name') }}</a>
        <nav>
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
            <a href="{{ route('campaigns.index') }}" @class(['active' => request()->routeIs('campaigns.*')])>Campaigns</a>
            <a href="{{ route('templates.index') }}" @class(['active' => request()->routeIs('templates.*')])>Templates</a>
            <a href="{{ route('audiences.index') }}" @class(['active' => request()->routeIs('audiences.*')])>Audiences</a>
            <a href="{{ route('settings.edit') }}" @class(['active' => request()->routeIs('settings.*')])>Settings</a>
        </nav>
        <form method="post" action="{{ route('logout') }}" class="logout">
            @csrf
            <span class="muted small">{{ auth()->user()->name }}</span>
            <button class="link">Log out</button>
        </form>
    </aside>
    <main class="main">
        @if (! app(\App\Services\MailConfig::class)->isConfigured() && ! request()->routeIs('settings.*'))
            <div class="flash flash-warn">Emails are only written to the log until you <a href="{{ route('settings.edit') }}">connect a sending provider</a>.</div>
        @endif
        @include('partials.flash')
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
