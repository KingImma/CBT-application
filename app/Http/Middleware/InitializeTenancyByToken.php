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

        if (!$bearer || !str_contains($bearer, '::')) {
            return response()->json(['message' => 'Unauthenticated or invalid routing token format.'], 401);
        }

        [$tenantSlug, $sanctumToken] = explode('::', $bearer, 2);

        $tenant = \App\Models\Tenant::where('slug', $tenantSlug)
            ->orWhere('id', $tenantSlug)
            ->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 404);
        }

        // 1. Switch the Database
        $this->tenancy->initialize($tenant);

        // 2. Fetch the token directly (This is what your debug route proved works!)
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($sanctumToken);

        if (! $tokenModel || ! $tokenModel->tokenable) {
            return response()->json(['message' => 'Token is invalid or has expired.'], 401);
        }

        // 3. Manually authenticate the user into the 'tenant' guard
        \Illuminate\Support\Facades\Auth::guard('tenant')->setUser($tokenModel->tokenable);

        // 4. Update the token's last used timestamp (replicates native Sanctum behavior)
        $tokenModel->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}