<?php

use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\TenantController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Support\Facades\Route;

// Super Admin auth — public
Route::prefix('super-admin')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
}); 

// Super Admin protected routes
Route::prefix('super-admin')
    ->middleware(['auth:sanctum', EnsureUserIsSuperAdmin::class])
    ->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',     [AuthController::class, 'me']);

        // Tenant management
        Route::prefix('tenants')->group(function () {
            Route::get('/',                [TenantController::class, 'index']);
            Route::post('/',               [TenantController::class, 'store']);
            Route::get('/{id}',            [TenantController::class, 'show']);
            Route::patch('/{id}',          [TenantController::class, 'update']);
            Route::post('/{id}/suspend',   [TenantController::class, 'suspend']);
            Route::post('/{id}/reinstate', [TenantController::class, 'reinstate']);
            Route::delete('/{id}',         [TenantController::class, 'destroy']);
        });
});