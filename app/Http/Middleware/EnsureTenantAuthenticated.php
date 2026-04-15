<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        // Explicitly check the tenant guard we set in the previous step
        $user = \Illuminate\Support\Facades\Auth::guard('tenant')->user();

        if (! $user) {
            return response()->json(['message' => 'Auth session lost.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account deactivated. Contact administration.'], 403);
        }

        if (! empty($roles) && ! $user->hasAnyRole($roles)) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return $next($request);
    }
}