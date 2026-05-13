<?php
// - Adds Cache-Control headers to API responses that don't change frequently
// - What: sets Cache-Control: private, max-age=<seconds> on successful GET responses
// - Why: prevents browsers from re-fetching reference data on every navigation
// - Usage: Route::middleware(['cache:3600'])->group(...)
// - Deliverable: less chatty frontend, fewer DB queries for static reference data

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetCacheHeaders
{
    public function handle(Request $request, Closure $next, string $maxAge = '300'): mixed
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && $response->isSuccessful()
        ) {
            $response->headers->set('Cache-Control', 'private, max-age=' . (int) $maxAge);
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + (int) $maxAge) . ' GMT');
        }

        return $response;
    }
}
