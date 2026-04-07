<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user('tenant');

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account deactivated.'], 403);
        }

        // Role check if roles are passed to middleware
        if (! empty($roles) && ! $user->hasAnyRole($roles)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}