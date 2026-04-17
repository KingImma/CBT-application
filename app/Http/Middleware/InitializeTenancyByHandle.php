<?php
// What - Resolves tenant from request subdomain (handle)
// Why - handle is the subdomain: greenfield.local.com -> handle = greenfield
// Deliverable - all tenant routes work without X-header

declare(strict_types=1);

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;

class InitializeTenancyByHandle
{

    public function __construct(private readonly Tenancy $tenancy)
    
    public function handle(Request $request, Closure $next): Response
    {
        $handle = $this->resolveHandle($request);
        
        if (! $handle) {
            return response()->json([
                'success' => false,
                'message' => 'Could not identify school. Check the URL.',
            ], 400)
        }
        
        $tenant = Tenant::where('handle', $handle)
            ->orWhere('slug', $handle)
            ->first();
        
        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => "No school found for '${handle}'."
            ], 404);
        }
        
        if (! $tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This school account is inactive'
            ])
        }
    }
    
    private function resolveHandle(Request $request): ?string
    {
        $host = $request->getHost();
        $appDomain = config('app.central_domain', 'localhost');
        $parts = explode('.', $host);
        
        $appParts = explode('.', $appDomain);
        if (count($parts) > count($appParts)) {
            return $parts[0]
        }
        
        
    }
}
