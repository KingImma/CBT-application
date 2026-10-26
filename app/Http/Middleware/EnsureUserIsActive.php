<?php

namespace App\Http\Middleware;

use App\Shared\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (! $user) {
            return ApiResponse::error('You must be logged in to access this resource', 401);
        }
        
        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();
            return ApiResponse::error('Account deactivated. Contact administration.', 403);
        }

        return $next($request);
    }
}
