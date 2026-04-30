<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            
            // 1. Public API Routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
                
            // 2. Super Admin Routes
            Route::middleware(['api', 'auth:super_admin', 'super-admin'])
                ->prefix('api/super-admin')
                ->group(base_path('routes/super_admin.php'));
            
            // 3. Tenant Routes
            Route::middleware(['api', 'tenant.header', 'auth:tenant', 'user.active'])
                ->prefix('api')
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            
            // --- YOUR CUSTOM PRIORITY INJECTION ---
            \App\Http\Middleware\InitializeTenancyByHeader::class, // Force DB switch first
            \Illuminate\Auth\Middleware\Authenticate::class,       // Then run auth:tenant
            \App\Http\Middleware\EnsureUserIsActive::class,        // Then check if active
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,          // Spatie role checks
        ]);
                
        $middleware->alias([
            'plan.limits' => \App\Http\Middleware\EnforceTenantPlanLimits::class,
            'tenant.header' => \App\Http\Middleware\InitializeTenancyByHeader::class,
            'user.active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'super-admin' => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
            'auth.any' => \App\Http\Middleware\AuthenticateAnyGuard::class,
            
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Prevent auth middleware from redirecting to non-existent login route
        \Illuminate\Auth\Middleware\Authenticate::redirectUsing(function ($request) {
            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        // Force JSON responses for all API routes natively
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
        
    })->create();