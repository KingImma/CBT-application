<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

class InitializeTenancyByToken
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();

        // 1. Ensure the token exists and contains our custom delimiter
        if (!$bearer || !str_contains($bearer, '::')) {
            return response()->json([
                'message' => 'Unauthenticated or invalid routing token format.'
            ], 401);
        }

        // 2. Split the token: "lekki-british" and "1|asdfkajshdfkjasdf"
        [$tenantSlug, $sanctumToken] = explode('::', $bearer, 2);

        // 3. Find the tenant
        $tenant = \App\Models\Tenant::where('slug', $tenantSlug)
            ->orWhere('id', $tenantSlug)
            ->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 404);
        }

        // 4. Initialize the isolated database connection
        $this->tenancy->initialize($tenant);

        // 5. CRITICAL: Strip the slug out of the request header. 
        // We replace it with the pure Sanctum token so the 'auth:sanctum' 
        // middleware running directly after this doesn't fail.
        $request->headers->set('Authorization', 'Bearer ' . $sanctumToken);

        return $next($request);
    }
}