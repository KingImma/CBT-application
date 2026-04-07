<?php

use App\Http\Controllers\Api\Tenant\AuthController;
use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\TermController;
use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\SubjectController;
use App\Http\Controllers\Api\Tenant\GradingScaleController;
use App\Http\Controllers\Api\Tenant\SchoolSettingController;
use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\EnsureTenantAuthenticated;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware([InitializeTenancyByHeader::class])->group(function () {

    // ── Public tenant routes (no auth needed) ────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/login',  [AuthController::class, 'login']);
    });

    // ── Authenticated tenant routes ───────────────────────────────────────────
    Route::middleware([EnsureTenantAuthenticated::class])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me',     [AuthController::class, 'me']);

        // Academic sessions
        Route::prefix('academic-sessions')->group(function () {
            Route::get('/',                         [AcademicSessionController::class, 'index']);
            Route::post('/',                        [AcademicSessionController::class, 'store']);
            Route::get('/{id}',                     [AcademicSessionController::class, 'show']);
            Route::patch('/{id}',                   [AcademicSessionController::class, 'update']);
            Route::delete('/{id}',                  [AcademicSessionController::class, 'destroy']);
            Route::post('/{id}/set-current',        [AcademicSessionController::class, 'setCurrent']);

            // Terms nested under sessions
            Route::prefix('/{sessionId}/terms')->group(function () {
                Route::get('/',                     [TermController::class, 'index']);
                Route::post('/',                    [TermController::class, 'store']);
                Route::patch('/{id}',               [TermController::class, 'update']);
                Route::delete('/{id}',              [TermController::class, 'destroy']);
                Route::post('/{id}/set-current',    [TermController::class, 'setCurrent']);
            });
        });

        // Class levels — already seeded, mainly read + arm management
        Route::prefix('class-levels')->group(function () {
            Route::get('/', fn() => response()->json(
                \App\Models\Tenant\ClassLevel::withCount(['classArms', 'students'])->get()
            ));

            // Class arms nested under class levels
            Route::prefix('/{classLevelId}/arms')->group(function () {
                Route::get('/',         [ClassArmController::class, 'index']);
                Route::post('/',        [ClassArmController::class, 'store']);
                Route::patch('/{id}',   [ClassArmController::class, 'update']);
                Route::delete('/{id}',  [ClassArmController::class, 'destroy']);
            });
        });

        // Subjects
        Route::prefix('subjects')->group(function () {
            Route::get('/',                                 [SubjectController::class, 'index']);
            Route::post('/',                                [SubjectController::class, 'store']);
            Route::get('/{id}',                             [SubjectController::class, 'show']);
            Route::patch('/{id}',                           [SubjectController::class, 'update']);
            Route::delete('/{id}',                          [SubjectController::class, 'destroy']);
            Route::post('/{id}/assign-teacher',             [SubjectController::class, 'assignTeacher']);
            Route::delete('/{id}/assignments/{assignmentId}', [SubjectController::class, 'removeTeacher']);
        });

        // Grading scales
        Route::prefix('grading-scales')->group(function () {
            Route::get('/',         [GradingScaleController::class, 'index']);
            Route::post('/',        [GradingScaleController::class, 'store']);
            Route::patch('/{id}',   [GradingScaleController::class, 'update']);
            Route::delete('/{id}',  [GradingScaleController::class, 'destroy']);
        });

        // School settings
        Route::get('/school-settings',    [SchoolSettingController::class, 'index']);
        Route::patch('/school-settings',  [SchoolSettingController::class, 'update']);
    });
});