<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

// Super Admin
use App\Http\Controllers\Api\SuperAdmin\AuthController as SuperAdminAuthController;
use App\Http\Controllers\Api\SuperAdmin\AnalyticsController;
use App\Http\Controllers\Api\SuperAdmin\TenantController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;

// Tenant
use App\Http\Controllers\Api\Tenant\AuthController as TenantAuthController;
use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\TermController;
use App\Http\Controllers\Api\Tenant\PasswordController;
use App\Http\Controllers\Api\Tenant\StudentImportController;
use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\ClassLevelController;
use App\Http\Controllers\Api\Tenant\SubjectController;
use App\Http\Controllers\Api\Tenant\GradingScaleController;
use App\Http\Controllers\Api\Tenant\SchoolSettingController;
use App\Http\Controllers\Api\Tenant\TeacherController;
use App\Http\Controllers\Api\Tenant\StudentController;

use App\Http\Middleware\InitializeTenancyByToken;

use App\Http\Middleware\EnsureTenantAuthenticated;


// SUPER ADMIN ROUTES (Central Database)
Route::prefix('super-admin')->group(function () {
    Route::post('login', [SuperAdminAuthController::class, 'login']);

    Route::middleware(['auth:super_admin', EnsureUserIsSuperAdmin::class])->group(function () {
        Route::post('logout', [SuperAdminAuthController::class, 'logout']);
        Route::get('me', [SuperAdminAuthController::class, 'me']);
        
        // Analytics & Logs
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
        Route::get('/analytics/usage',    [AnalyticsController::class, 'usage']);
        Route::get('/audit-logs',         [AnalyticsController::class, 'auditLogs']);
        
        // Subscription Plans
        Route::get('/plans', [SubscriptionPlanController::class, 'index']);
        Route::get('/plans/{id}', [SubscriptionPlanController::class, 'show']);
        Route::post('/plans',       [SubscriptionPlanController::class, 'store']);
        Route::put('/plans/{id}',   [SubscriptionPlanController::class, 'update']);
        Route::delete('/plans/{id}',[SubscriptionPlanController::class, 'destroy']);

        // Tenant Management
        Route::prefix('tenants')->group(function () {
            Route::get('/',               [TenantController::class, 'index']);
            Route::post('/',              [TenantController::class, 'store']);
            Route::get('/{id}',           [TenantController::class, 'show']);
            Route::patch('/{id}',         [TenantController::class, 'update']);
            Route::post('/{id}/suspend',  [TenantController::class, 'suspend']);
            Route::post('/{id}/reinstate',[TenantController::class, 'reinstate']);
            Route::delete('/{id}',        [TenantController::class, 'destroy']);
        });
    });
});

// TENANT AUTH ROUTES 
Route::prefix('auth')->group(function () {
    Route::post('/login',           [TenantAuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);
    Route::post('/reset-password',  [PasswordController::class, 'resetPassword']);
});

// CSV template — no auth needed so admin can share link freely
Route::get('/students/import/template', [StudentImportController::class, 'downloadTemplate']);

// PROTECTED TENANT ROUTES
Route::middleware([
    InitializeTenancyByToken::class,
    EnsureTenantAuthenticated::class
])->group(function () {

    Route::post('/auth/logout',          [TenantAuthController::class, 'logout']);
    Route::get('/auth/me',               [TenantAuthController::class, 'me']);
    Route::post('/auth/change-password', [PasswordController::class, 'change']);

    // Academic sessions
    Route::prefix('academic-sessions')->group(function () {
        // Custom
        Route::post('/{id}/set-current',     [AcademicSessionController::class, 'setCurrent']);
        
        // CRUD
        Route::get('/',                      [AcademicSessionController::class, 'index']);
        Route::post('/',                     [AcademicSessionController::class, 'store']);
        Route::get('/{id}',                  [AcademicSessionController::class, 'show']);
        Route::patch('/{id}',                [AcademicSessionController::class, 'update']);
        Route::delete('/{id}',               [AcademicSessionController::class, 'destroy']);

        // Terms nested under sessions
        Route::prefix('/{sessionId}/terms')->group(function () {
            // Custom
            Route::post('/{id}/set-current', [TermController::class, 'setCurrent']);
            
            // CRUD
            Route::get('/',                  [TermController::class, 'index']);
            Route::post('/',                 [TermController::class, 'store']);
            Route::patch('/{id}',            [TermController::class, 'update']);
            Route::delete('/{id}',           [TermController::class, 'destroy']);
        });
    });

    // Class levels
    Route::prefix('class-levels')->group(function () {
        Route::get('/',        [ClassLevelController::class, 'index']);
        Route::post('/',       [ClassLevelController::class, 'store']);
        Route::get('/{id}',    [ClassLevelController::class, 'show']);
        Route::patch('/{id}',  [ClassLevelController::class, 'update']);
        Route::delete('/{id}', [ClassLevelController::class, 'destroy']);
    
        // Arms nested under class level
        Route::prefix('/{classLevelId}/arms')->group(function () {
            Route::get('/',        [ClassArmController::class, 'index']);
            Route::post('/',       [ClassArmController::class, 'store']);
            Route::patch('/{id}',  [ClassArmController::class, 'update']);
            Route::delete('/{id}', [ClassArmController::class, 'destroy']);
        });
    });

    // Subjects
    Route::prefix('subjects')->group(function () {
        // Custom
        Route::post('/{id}/assign-teacher',               [SubjectController::class, 'assignTeacher']);
        Route::delete('/{id}/assignments/{assignmentId}', [SubjectController::class, 'removeTeacher']);
        
        // CRUD
        Route::get('/',                                   [SubjectController::class, 'index']);
        Route::post('/',                                  [SubjectController::class, 'store']);
        Route::get('/{id}',                               [SubjectController::class, 'show']);
        Route::patch('/{id}',                             [SubjectController::class, 'update']);
        Route::delete('/{id}',                            [SubjectController::class, 'destroy']);
    });
    
    // Teachers
    Route::prefix('teachers')->group(function () {
        // Custom
        Route::post('/{id}/toggle-active',  [TeacherController::class, 'toggleActive']);
        Route::post('/{id}/reset-password', [TeacherController::class, 'resetPassword']);
        Route::post('/{id}/restore',        [TeacherController::class, 'restore']);
        
        // CRUD
        Route::get('/',                     [TeacherController::class, 'index']);
        Route::post('/',                    [TeacherController::class, 'store']);
        Route::get('/{id}',                 [TeacherController::class, 'show']);
        Route::patch('/{id}',               [TeacherController::class, 'update']);
        Route::delete('/{id}',              [TeacherController::class, 'destroy']);
    });

    // Students
    Route::prefix('students')->group(function () {
        // Custom
        Route::post('/{id}/toggle-active',   [StudentController::class, 'toggleActive']);
        Route::post('/{id}/reassign-class',  [StudentController::class, 'reassignClass']);
        Route::post('/import',               [StudentImportController::class, 'import']);
        Route::get('/export',                [StudentController::class, 'exportCsv']);
        Route::post('/bulk-reset-passwords', [StudentController::class, 'bulkResetPasswords']);
        
        // CRUD
        Route::get('/',                      [StudentController::class, 'index']);
        Route::post('/',                     [StudentController::class, 'store']);
        Route::get('/{id}',                  [StudentController::class, 'show']);
        Route::patch('/{id}',                [StudentController::class, 'update']);
    });

    // Grading scales
    Route::prefix('grading-scales')->group(function () {
        Route::get('/',         [GradingScaleController::class, 'index']);
        Route::post('/',        [GradingScaleController::class, 'store']);
        Route::get('/{id}',    [GradingScaleController::class, 'show']);
        Route::patch('/{id}',   [GradingScaleController::class, 'update']);
        Route::delete('/{id}',  [GradingScaleController::class, 'destroy']);
    });

    // School settings
    Route::get('/school-settings',   [SchoolSettingController::class, 'index']);
    Route::patch('/school-settings', [SchoolSettingController::class, 'update']);
});