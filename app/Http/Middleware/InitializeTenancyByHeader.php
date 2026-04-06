<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        $tenant = \App\Models\Tenant::where('id', $tenantSlug)
            ->orWhere('slug', $tenantSlug)
            ->first();

        if (! $tenant) {
            return response()->json([
                'message' => "Tenant '{$tenantSlug}' not found.",
            ], 404);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}