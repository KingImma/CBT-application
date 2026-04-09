<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Stancl\Tenancy\Tenancy;

class InitializeTenancyByHeaderOrToken
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $slug = $request->header('X-Tenant')
            ?? $this->resolveSlugFromToken($request);

        if (! $slug) {
            return response()->json(['message' => 'Tenant context missing.'], 400);
        }

        $tenant = \App\Models\Tenant::where('id', $slug)
            ->orWhere('slug', $slug)
            ->first();

        if (! $tenant) {
            return response()->json(['message' => "Tenant '{$slug}' not found."], 404);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }

    private function resolveSlugFromToken(Request $request): ?string
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return null;
        }

        $record = PersonalAccessToken::findToken($bearerToken);

        if (! $record || ! str_contains($record->name, ':')) {
            return null;
        }

        // Token name format: "tenant-token:kings-college-lagos"
        return explode(':', $record->name, 2)[1];
    }
}