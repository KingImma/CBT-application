<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('super_admin');

        if (! $user instanceof SuperAdmin) {
            // Fallback for backward compatibility
            $user = $request->user();
            if ($user instanceof SuperAdmin) {
                return $next($request);
            }
            return ApiResponse::error('Forbidden. Administrator access required.', 403);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Forbidden. Account is deactivated.', 403);
        }

        return $next($request);
    }
}
