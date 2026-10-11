<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users', ['users' => User::withCount('bills')->orderBy('username')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'alpha_dash:ascii', 'min:3', 'max:60', 'unique:users,username'],
            'password' => ['required', Password::min(8)],
        ]);
        $user = new User(['username' => $data['username'], 'password' => $data['password']]);
        $user->is_admin = $request->boolean('is_admin');
        $user->save();

        return back()->with('status', "User '{$user->username}' created.");
    }

    public function password(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', Password::min(8)]]);
        $user->update(['password' => $data['password']]);

        return back()->with('status', "Password of '{$user->username}' changed.");
    }

    public function revokeToken(User $user)
    {
        $user->revokeApiToken();

        return back()->with('status', "API token of '{$user->username}' removed.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        $user->delete();

        return back()->with('status', 'User deleted.');
    }
}
