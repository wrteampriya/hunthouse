<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class CheckAuth
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            // Sanctum tokens are formatted as "<id>|<plaintext>"; use Sanctum's
            // own resolver so the "id|token" format is parsed correctly.
            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken && $accessToken->tokenable) {
                Auth::login($accessToken->tokenable);
            }
        }

        return $next($request);
    }
}
