<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByHeader
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $handle = $request->header('X-Tenant');

        if (! $handle) {
            return $next($request);
        }

        $tenant = Cache::remember(
            "tenant:handle:{$handle}",
            3600,
            fn () => Tenant::where('handle', $handle)->first()
        );

        if (! $tenant) {
            return ApiResponse::error("School '{$handle}' not found.", 404);
        }

        if (! $tenant->is_active) {
            return ApiResponse::error('This school account is inactive.', 403);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
