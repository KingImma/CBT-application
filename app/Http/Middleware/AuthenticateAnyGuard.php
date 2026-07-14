<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateAnyGuard
{
    public function handle(Request $request, Closure $next, ...$guards): mixed
    {
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::shouldUse($guard);

                return $next($request);
            }
        }

        return ApiResponse::error('You must be logged in to access this resource.', 401);
    }
}
