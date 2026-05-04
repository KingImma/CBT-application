<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        // Sanctum already guaranteed $request->user() exists by this point.
        // We just need to check if they have been banned/deactivated.
        if (! $request->user()->is_active) {
            // Optional: Auto-revoke the token so they have to log in again if reactivated
            $request->user()->currentAccessToken()->delete();

            return ApiResponse::error('Account deactivated. Contact administration.', 403);
        }

        return $next($request);
    }
}
