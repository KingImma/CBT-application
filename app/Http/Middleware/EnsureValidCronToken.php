<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidCronToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('cron.token');
        $provided = (string) $request->header('X-Cron-Token', '');

        // Fail closed: if the token isn't configured, nobody gets in.
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return $next($request);
    }
}
