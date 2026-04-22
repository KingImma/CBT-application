<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Tenant\PasswordController;
use App\Http\Controllers\Api\Tenant\StudentImportController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Middleware\InitializeTenancyByHeader;


/*
 * 1. What it is: `routes/api.php` (Public routes).
 * 2. What it does in a nutshell: Holds ONLY routes that do not require an active user session.
 * 3. Why this was chosen: Keeps the entry points (login, password resets, public downloads) entirely isolated from protected business logic.
 * 4. Expected deliverables and alternatives: A tiny, highly readable file for unauthenticated endpoints.
 */

Route::middleware([InitializeTenancyByHeader::class])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::controller(PasswordController::class)->prefix('auth')->group(function () {
        Route::post('/forgot-password', 'forgotPassword');
        Route::post('/reset-password', 'resetPassword');
    });
});

Route::get('/students/import/template', [StudentImportController::class, 'downloadTemplate']);

Route::prefix('onboarding')->controller(OnboardingController::class)->group(function () {
    Route::get('/check-handle', 'checkHandle');
    Route::post('/register', 'register');
});