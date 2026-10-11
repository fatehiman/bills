@extends('layouts.app')
@section('title', 'Users')
@section('content')
    <div class="page-head"><h1>Users</h1></div>

    <div class="card" style="margin-bottom:20px">
        <h2>Add user</h2>
        <form method="post" action="{{ route('admin.users.store') }}" class="two" style="align-items:end">@csrf
            <div class="field"><label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="off" autocapitalize="none" required></div>
            <div class="field"><label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required></div>
            <div class="field"><label class="check"><input type="checkbox" name="is_admin" value="1"> Admin</label></div>
            <div class="field"><button class="btn" type="submit">@include('partials.icon', ['name' => 'plus']) Add user</button></div>
        </form>
    </div>

    <div class="card">
        <div class="tablewrap">
            <table>
                <thead><tr><th>User</th><th>Bills</th><th>API token</th><th>New password</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td><b>{{ $u->username }}</b> @if ($u->is_admin)<span class="pill">admin</span>@endif</td>
                        <td>{{ $u->bills_count }}</td>
                        <td>
                            @if ($u->api_token_hash)
                                <span class="pill ok">…{{ $u->api_token_hint }}</span>
                                <form method="post" action="{{ route('admin.users.token.revoke', $u) }}" class="inline" data-confirm="Remove the API token of this user?">@csrf @method('DELETE')
                                    <button class="btn red sm" type="submit">Remove</button></form>
                            @else
                                <span class="muted">none</span>
                            @endif
                        </td>
                        <td>
                            <form method="post" action="{{ route('admin.users.password', $u) }}" class="actions">@csrf
                                <input type="password" name="password" placeholder="min 8 characters" autocomplete="new-password" minlength="8" style="width:170px" required>
                                <button class="btn alt sm" type="submit">Set</button>
                            </form>
                        </td>
                        <td>
                            @if ($u->id !== auth()->id())
                                <form method="post" action="{{ route('admin.users.destroy', $u) }}" data-confirm="Delete user {{ $u->username }} and all their bills?">@csrf @method('DELETE')
                                    <button class="btn red sm icon" type="submit" title="Delete user">@include('partials.icon', ['name' => 'trash'])</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
