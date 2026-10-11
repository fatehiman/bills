<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function show()
    {
        return view('account.show', ['user' => auth()->user()]);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }

    public function rotateToken(Request $request)
    {
        $token = $request->user()->rotateApiToken();

        // Show the new token once, on the next page only.
        return redirect()->route('account')->with('new_token', $token)->with('status', 'New API token created. Copy it now. You cannot see it again.');
    }

    public function revokeToken(Request $request)
    {
        $request->user()->revokeApiToken();

        return redirect()->route('account')->with('status', 'API token removed.');
    }
}
