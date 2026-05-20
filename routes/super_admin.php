<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdmin\AnalyticsController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\Api\SuperAdmin\TenantController;
use Illuminate\Support\Facades\Route;

/*
 * 1. What it is: `routes/super_admin.php`.
 * 2. What it does in a nutshell: Contains all routes for managing the SaaS platform itself (billing, tenant suspension, global analytics).
 * 3. Why this was chosen: This is the exact Domain-Driven structure recommended in the articles. Super Admin logic should never share a file with Tenant logic. Note that we don't need `Route::middleware()` here because `bootstrap/app.php` already applied it.
 * 4. Expected deliverables and alternatives: A strictly isolated control plane for your application.
 */

Route::controller(AuthController::class)->group(function () {
    Route::post('/logout', 'logout');
    Route::get('/me', 'me');
});

Route::controller(AnalyticsController::class)->group(function () {
    Route::get('/analytics/overview', 'overview');
    Route::get('/analytics/usage', 'usage');
    Route::get('/audit-logs', 'auditLogs');
});

Route::apiResource('plans', SubscriptionPlanController::class);

Route::controller(TenantController::class)->prefix('tenants')->group(function () {
    Route::post('/{id}/suspend', 'suspend');
    Route::post('/{id}/reinstate', 'reinstate');
});
Route::apiResource('tenants', TenantController::class);
