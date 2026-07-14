<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdmin\AnalyticsController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\Api\SuperAdmin\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Resource
|--------------------------------------------------------------------------
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('/logout', 'logout');
    Route::get('/me', 'me');
});

/*
|--------------------------------------------------------------------------
| Analytics Resource
|--------------------------------------------------------------------------
*/

Route::controller(AnalyticsController::class)->group(function () {
    Route::get('/analytics/overview', 'overview');
    Route::get('/analytics/usage', 'usage');
    Route::get('/audit-logs', 'auditLogs');
});

/*
|--------------------------------------------------------------------------
| Subscription Plans Resource
|--------------------------------------------------------------------------
*/

Route::controller(SubscriptionPlanController::class)->group(function () {
    Route::apiResource('plans', SubscriptionPlanController::class);
});

/*
|--------------------------------------------------------------------------
| Tenants Resource
|--------------------------------------------------------------------------
*/

Route::controller(TenantController::class)->prefix('tenants')->group(function () {
    Route::post('/{id}/suspend', 'suspend');
    Route::post('/{id}/reinstate', 'reinstate');
});
Route::apiResource('tenants', TenantController::class);
