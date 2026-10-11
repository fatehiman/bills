@extends('layouts.app')
@section('title', 'Account & API')
@section('content')
    <div class="page-head"><h1>Account &amp; API</h1></div>

    <div class="two" style="align-items:start">
        <div class="card">
            <h2>@include('partials.icon', ['name' => 'key']) API token</h2>
            <p class="muted">Use the token to call the API from your own programs. You have no token until you create one.</p>

            @if (session('new_token'))
                <p><b>Your new token (shown only once):</b></p>
                <div class="token-box">
                    <code class="mono">{{ session('new_token') }}</code>
                    <button type="button" class="btn alt sm" data-copy="{{ session('new_token') }}">@include('partials.icon', ['name' => 'copy']) <span class="t">Copy</span></button>
                </div>
                <p class="hint">Save it in a safe place. We keep only a hash, so we cannot show it again.</p>
            @elseif ($user->api_token_hash)
                <p>
                    <span class="pill ok">Active</span>
                    Token ends with <b class="mono">…{{ $user->api_token_hint }}</b>,
                    created {{ $user->api_token_created_at?->diffForHumans() }}.
                </p>
            @else
                <p><span class="pill warn">No token</span></p>
            @endif

            <div class="actions">
                <form method="post" action="{{ route('account.token') }}"
                      @if ($user->api_token_hash) data-confirm="Create a new token? The old token will stop working immediately." @endif>@csrf
                    <button class="btn" type="submit">@include('partials.icon', ['name' => 'refresh']) {{ $user->api_token_hash ? 'Rotate token' : 'Create token' }}</button>
                </form>
                @if ($user->api_token_hash)
                    <form method="post" action="{{ route('account.token.revoke') }}" data-confirm="Remove the token? Programs that use it will stop working.">@csrf @method('DELETE')
                        <button class="btn red" type="submit">@include('partials.icon', ['name' => 'trash']) Remove token</button>
                    </form>
                @endif
            </div>

            <h3 style="margin-top:20px">Quick test</h3>
            <pre class="token-box mono" style="white-space:pre-wrap;font-size:.8rem"><code>curl -H "Authorization: Bearer YOUR_TOKEN" \
  {{ url('/api/v1/bills') }}</code></pre>
            <p class="hint">The full manual is in the file <b>reference-docs/api-usage.md</b> in the project repository.</p>
        </div>

        <div class="card">
            <h2>@include('partials.icon', ['name' => 'user']) Change password</h2>
            <form method="post" action="{{ route('account.password') }}">@csrf
                <div class="field"><label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
                <div class="field"><label for="password">New password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
                    <div class="hint">At least 8 characters.</div></div>
                <div class="field"><label for="password_confirmation">New password again</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
                <button class="btn" type="submit">Change password</button>
            </form>
        </div>
    </div>
@endsection
