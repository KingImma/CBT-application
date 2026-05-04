<?php

// What - Resolves tenant from request subdomain (handle)
// Why - handle is the subdomain: greenfield.local.com -> handle = greenfield
// Deliverable - all tenant routes work without X-header

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByHandle
{
    // FIX 1: Replaced the trailing semicolon with empty curly braces {}
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $handle = $this->resolveHandle($request);

        if (! $handle) {
            return ApiResponse::error('Could not identify school. Check the URL.', 400);
        }

        $tenant = Tenant::where('handle', $handle)
            ->orWhere('slug', $handle)
            ->first();

        if (! $tenant) {
            // FIX 2: Updated PHP string interpolation from deprecated '${handle}' to '{$handle}'
            return ApiResponse::error("No school found for '{$handle}'.", 404);
        }

        if (! $tenant->is_active) {
            // FIX 3: Added the missing 403 Forbidden status code.
            // Without this, the frontend receives a 200 OK and might try to proceed.
            return ApiResponse::error('This school account is inactive.', 403);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }

    private function resolveHandle(Request $request): ?string
    {
        $host = $request->getHost();

        // FIX 4: Safely check Stancl's central_domains array first, fallback to app.central_domain
        $centralDomains = config('tenancy.central_domains', []);
        $appDomain = ! empty($centralDomains)
            ? $centralDomains[0]
            : config('app.central_domain', 'localhost');

        $parts = explode('.', $host);

        $appParts = explode('.', $appDomain);
        if (count($parts) > count($appParts)) {
            return $parts[0];
        }

        return null;
    }
}
