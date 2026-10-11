<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/** Authenticates API calls with "Authorization: Bearer <token>". */
class ApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $user = $token ? User::where('api_token_hash', User::hashToken($token))->first() : null;

        if (! $user) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'unauthenticated', 'message' => 'Missing or invalid API token.'],
            ], 401);
        }

        $request->setUserResolver(fn () => $user);
        auth()->setUser($user);

        return $next($request);
    }
}
