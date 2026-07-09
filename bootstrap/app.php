<?php

use App\Http\Middleware\AuthenticateAnyGuard;
use App\Http\Middleware\EnforceTenantPlanLimits;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\InitializeTenancyByHeader;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {

            // Broadcasting auth — tenant users
            // Resulting path: /api/broadcasting/auth
            Broadcast::routes([
                'prefix' => 'api',
                'middleware' => ['api', 'tenant.header', 'auth:tenant', 'user.active'],
            ]);

            // Broadcasting auth — super admins
            // Resulting path: /api/super-admin/broadcasting/auth
            Broadcast::routes([
                'prefix' => 'api/super-admin',
                'middleware' => ['api', 'auth:super_admin', 'super-admin'],
            ]);

            require base_path('routes/channels.php');

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
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,

            InitializeTenancyByHeader::class,
            Authenticate::class,
            EnsureUserIsActive::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);

        $middleware->alias([
            'plan.limits' => EnforceTenantPlanLimits::class,
            'tenant.header' => InitializeTenancyByHeader::class,
            'user.active' => EnsureUserIsActive::class,
            'super-admin' => EnsureUserIsSuperAdmin::class,
            'auth.any' => AuthenticateAnyGuard::class,

            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        Authenticate::redirectUsing(function ($request) {
            return null;
        });
    })
    ->withCommands([
    __DIR__.'/../app/Data/Console/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

    })->create();
