<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        // 1. Let Sanctum provide the user
        $user = $request->user();

        // 2. Safety check in case it slipped past auth
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 3. Your custom business logic: Is the account suspended?
        if (! $user->is_active) {
            return response()->json(['message' => 'Account deactivated. Please contact administration.'], 403);
        }

        // 4. Role check if roles are passed to middleware (e.g., middleware('tenant.auth:teacher,admin'))
        if (! empty($roles) && ! $user->hasAnyRole($roles)) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return $next($request);
    }
}