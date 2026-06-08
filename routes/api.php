<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Middleware\InitializeTenancyByHeader;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
 * 1. What it is: `routes/api.php` (Public routes).
 * 2. What it does in a nutshell: Holds ONLY routes that do not require an active user session.
 * 3. Why this was chosen: Public downloads are entirely isolated from protected business logic.
 * 4. Expected deliverables and alternatives: A tiny, highly readable file for unauthenticated endpoints.
 */

Route::middleware([InitializeTenancyByHeader::class])->group(function () {
    Route::post("/auth/login", [AuthController::class, "login"])->middleware(
        "throttle:5,1",
    );

    Route::controller(PasswordController::class)
        ->prefix("auth")
        ->group(function () {
            Route::post("/forgot-password", "forgotPassword");
            Route::post("/reset-password", "resetPassword");
        });
});

Route::middleware([InitializeTenancyByHeader::class, "auth:tenant"])->post(
    "/broadcasting/auth",
    function () {
        return Broadcast::auth(request());
    },
);

Route::prefix("onboarding")
    ->controller(OnboardingController::class)
    ->group(function () {
        Route::get("/check-handle", "checkHandle")->middleware("throttle:10,1");
        Route::post("/register", "register");
        Route::get("/plans", "plans");
    });

Route::controller(PasswordController::class)
    ->middleware(["throttle:10,1", InitializeTenancyByHeader::class])
    ->prefix("password")
    ->group(function () {
        // Public routes - no auth required, but tenancy is initialized if X-Tenant header is present
        Route::post("/forgot", "forgot");
        Route::post("/verify-otp", "verifyOtp");
        Route::post("/reset", "reset");

        // Protected route - accepts super_admin or tenant users (school_admin, teacher)
        Route::middleware("auth.any:super_admin,tenant")->post(
            "/change",
            "change",
        );
    });
