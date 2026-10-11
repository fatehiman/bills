<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#6c5ce7">
    <title>@yield('title', 'Bills') - Bills</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<header class="topbar">
    <div class="wrap">
        <a class="logo" href="{{ route('dashboard') }}"><span class="mark">@include('partials.icon', ['name' => 'receipt'])</span> Bills</a>
        <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Menu">
        <label for="nav-toggle" class="burger">@include('partials.icon', ['name' => 'menu'])</label>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard', 'bills.*') ? 'on' : '' }}">@include('partials.icon', ['name' => 'home']) My bills</a>
            <a href="{{ route('account') }}" class="{{ request()->routeIs('account') ? 'on' : '' }}">@include('partials.icon', ['name' => 'key']) Account &amp; API</a>
            @if (auth()->user()->is_admin)
                <a href="{{ route('admin.providers') }}" class="{{ request()->routeIs('admin.providers') ? 'on' : '' }}">@include('partials.icon', ['name' => 'plug']) Providers</a>
                <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'on' : '' }}">@include('partials.icon', ['name' => 'users']) Users</a>
            @endif
            <form method="post" action="{{ route('logout') }}" class="inline">@csrf
                <button class="link" type="submit">@include('partials.icon', ['name' => 'logout']) Log out ({{ auth()->user()->username }})</button>
            </form>
        </nav>
    </div>
</header>
<main>
    <div class="wrap">
        @include('partials.flash')
        @yield('content')
    </div>
</main>
<footer>Bills &middot; amounts are in Rial</footer>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
