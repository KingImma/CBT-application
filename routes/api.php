<?php

use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\AnalyticsController;
use App\Http\Controllers\Api\SuperAdmin\TenantController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

// Health check
Route::get('/health', function () {
    try {
        Redis::connection()->command('ping');

        return response()->json([
            'redis'  => 'connected',
            'driver' => config('database.redis.client'),
            'queue'  => config('queue.default'),
            'cache'  => config('cache.default'),
            'session'=> config('session.driver'),
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug-domain', function () {
    return response()->json([
        'env_value' => env('CENTRAL_DOMAIN'),
        'app_config' => config('app.central_domain'),
        'tenancy_config' => config('tenancy.central_domains'),
    ]);
});

// Sybscription routes
Route::get('/plans', [SubscriptionPlanController::class, 'index']);
Route::get('/plans/{id}', [SubscriptionPlanController::class, 'show']);

// Super Admin auth — public
Route::prefix("super-admin")->group(function () {
    Route::post("login", [AuthController::class, "login"]);
});

// Super Admin protected routes
Route::prefix("super-admin")
    ->middleware(["auth:sanctum", EnsureUserIsSuperAdmin::class])
    ->group(function () {
        Route::post("logout", [AuthController::class, "logout"]);
        Route::get("me", [AuthController::class, "me"]);
        
        // Platform analytics
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
        Route::get('/analytics/usage',    [AnalyticsController::class, 'usage']);
        Route::get('/audit-logs',         [AnalyticsController::class, 'auditLogs']);
        
        // Subscription plans CRUD
        Route::get('/plans',        [SubscriptionPlanController::class, 'index']);
        Route::post('/plans',       [SubscriptionPlanController::class, 'store']);
        Route::put('/plans/{id}',   [SubscriptionPlanController::class, 'update']);
        Route::delete('/plans/{id}',[SubscriptionPlanController::class, 'destroy']);

        // Tenant management
        Route::prefix("tenants")->group(function () {
            Route::get("/", [TenantController::class, "index"]);
            Route::post("/", [TenantController::class, "store"]);
            Route::get("/{id}", [TenantController::class, "show"]);
            Route::patch("/{id}", [TenantController::class, "update"]);
            Route::post("/{id}/suspend", [TenantController::class, "suspend"]);
            Route::post("/{id}/reinstate", [
                TenantController::class,
                "reinstate",
            ]);
            Route::delete("/{id}", [TenantController::class, "destroy"]);
        });
    });
