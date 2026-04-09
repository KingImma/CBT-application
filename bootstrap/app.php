<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'plan.limits' => \App\Http\Middleware\EnforceTenantPlanLimits::class,
            'tenant.auth' => \App\Http\Middleware\EnsureTenantAuthenticated::class,
            'tenant.header' => \App\Http\Middleware\InitializeTenancyByHeaderOrToken::class,
            'super-admin' => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
            
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
