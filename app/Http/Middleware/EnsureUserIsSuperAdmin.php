<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SuperAdmin;

class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof SuperAdmin) {
            return response()->json(['message' => 'Forbidden. Administrator access required.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Forbidden. Account is deactivated.'], 403);
        }

        return $next($request);
    }
}

