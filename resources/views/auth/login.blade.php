@extends('layouts.guest')
@section('title', 'Log in')
@section('content')
    <div class="login card">
        <div class="hero" style="margin-bottom:12px">
            <div class="mark" style="width:64px;height:64px;border-radius:20px">@include('partials.icon', ['name' => 'receipt'])</div>
            <h2>Log in</h2>
        </div>
        @include('partials.flash')
        <form method="post" action="/mainadmin">
            @csrf
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label></div>
            <button class="btn" style="width:100%" type="submit">Log in</button>
        </form>
    </div>
@endsection
