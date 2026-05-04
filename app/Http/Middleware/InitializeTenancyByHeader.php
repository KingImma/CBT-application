<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByHeader
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $handle = $request->header('X-Tenant');

        // If no header is present, assume this is a Super Admin request
        // and proceed using the central database.
        if (! $handle) {
            return $next($request);
        }

        $tenant = Tenant::where('handle', $handle)->first();

        if (! $tenant) {
            return ApiResponse::error("School '{$handle}' not found.", 404);
        }

        if (! $tenant->is_active) {
            return ApiResponse::error('This school account is inactive.', 403);
        }

        // Header exists and school is valid: hot-swap the database
        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
