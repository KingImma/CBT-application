<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Tenancy;

class InitializeTenancyByHeader
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $tenantSlug = $request->header('X-Tenant');

        if (! $tenantSlug) {
            return response()->json([
                'message' => 'Missing X-Tenant header.',
            ], 400);
        }

        // Safely determine if we should search by UUID or Slug
        $tenant = Str::isUuid($tenantSlug)
            ? \App\Models\Tenant::find($tenantSlug)
            : \App\Models\Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            return response()->json([
                'message' => "Tenant '{$tenantSlug}' not found.",
            ], 404);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}