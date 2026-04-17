<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;

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
            return response()->json(['success' => false, 'message' => "School '{$handle}' not found."], 404);
        }

        if (! $tenant->is_active) {
            return response()->json(['success' => false, 'message' => 'This school account is inactive.'], 403);
        }

        // Header exists and school is valid: hot-swap the database
        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}