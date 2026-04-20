<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Auth
use App\Http\Controllers\Api\AuthController;

// Super Admin
use App\Http\Controllers\Api\SuperAdmin\AnalyticsController;
use App\Http\Controllers\Api\SuperAdmin\TenantController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;

// Tenant
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
use App\Http\Controllers\Api\Tenant\ClassArmSubjectController;

// Middleware
use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\EnsureTenantAuthenticated;


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (Requires Optional Tenancy Middleware)
|--------------------------------------------------------------------------
*/
// The middleware runs first. If 'X-Tenant' is present, it swaps the DB. 
// If not, it safely passes it to the central DB.
Route::middleware([InitializeTenancyByHeader::class])->group(function () {
    
    // Unified Login
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    // Password reset (Requires tenant DB to find the user)
    Route::post('/auth/forgot-password', [PasswordController::class, 'forgotPassword']);
    Route::post('/auth/reset-password',  [PasswordController::class, 'resetPassword']);
});

// CSV template — no auth needed, public asset
Route::get('/students/import/template', [StudentImportController::class, 'downloadTemplate']);


/*
|--------------------------------------------------------------------------
| SUPER ADMIN ROUTES (Central Database)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:super_admin', EnsureUserIsSuperAdmin::class])->group(function () {
    
    // Unified Logout & Me
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    
    // Analytics & Logs
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/usage',    [AnalyticsController::class, 'usage']);
    Route::get('/audit-logs',         [AnalyticsController::class, 'auditLogs']);
    
    // Subscription Plans
    Route::apiResource('plans', SubscriptionPlanController::class);

    // Tenant Management
    Route::apiResource('tenants', TenantController::class);
    Route::post('/tenants/{id}/suspend',   [TenantController::class, 'suspend']);
    Route::post('/tenants/{id}/reinstate', [TenantController::class, 'reinstate']);
});


/*
|--------------------------------------------------------------------------
| PROTECTED TENANT ROUTES (Schools)
|--------------------------------------------------------------------------
*/
Route::middleware([
    InitializeTenancyByHeader::class,
    EnsureTenantAuthenticated::class
])->group(function () {

    // Unified Logout & Me
    Route::post('/auth/logout',          [AuthController::class, 'logout']);
    Route::get('/auth/me',               [AuthController::class, 'me']);
    
    Route::post('/auth/change-password', [PasswordController::class, 'change']);

    // Academic sessions & Terms
    Route::prefix('academic-sessions')->group(function () {
        Route::post('/{id}/set-current', [AcademicSessionController::class, 'setCurrent']);
        Route::get('/',                  [AcademicSessionController::class, 'index']);
        Route::post('/',                 [AcademicSessionController::class, 'store']);
        Route::get('/{id}',              [AcademicSessionController::class, 'show']);
        Route::patch('/{id}',            [AcademicSessionController::class, 'update']);
        Route::delete('/{id}',           [AcademicSessionController::class, 'destroy']);

        Route::prefix('/{sessionId}/terms')->group(function () {
            Route::post('/{id}/set-current', [TermController::class, 'setCurrent']);
            Route::get('/',                  [TermController::class, 'index']);
            Route::post('/',                 [TermController::class, 'store']);
            Route::patch('/{id}',            [TermController::class, 'update']);
            Route::delete('/{id}',           [TermController::class, 'destroy']);
        });
    });

    // Class levels & Arms
    Route::prefix('class-levels')->group(function () {
        Route::get('/',        [ClassLevelController::class, 'index']);
        Route::post('/',       [ClassLevelController::class, 'store']);
        Route::get('/{id}',    [ClassLevelController::class, 'show']);
        Route::patch('/{id}',  [ClassLevelController::class, 'update']);
        Route::delete('/{id}', [ClassLevelController::class, 'destroy']);
    
        Route::prefix('/{classLevelId}/arms')->group(function () {
            Route::get('/',        [ClassArmController::class, 'index']);
            Route::post('/',       [ClassArmController::class, 'store']);
            Route::patch('/{id}',  [ClassArmController::class, 'update']);
            Route::delete('/{id}', [ClassArmController::class, 'destroy']);
        });
        
        Route::prefix('/{classLevelId}/arms/{armId}/subjects')->group(function () {
            // GET  — list assigned + unassigned subjects for this arm
            Route::get('/',                        [ClassArmSubjectController::class, 'index']);
        
            // POST /sync — replace entire subject list atomically
            Route::post('/sync',                   [ClassArmSubjectController::class, 'sync']);
        
            // POST /inherit — copy all class-level subjects to this arm
            Route::post('/inherit',                [ClassArmSubjectController::class, 'inheritFromLevel']);
        
            // POST /{subjectId} — add one subject
            Route::post('/{subjectId}',            [ClassArmSubjectController::class, 'attach']);
        
            // DELETE /{subjectId} — remove one subject
            Route::delete('/{subjectId}',          [ClassArmSubjectController::class, 'detach']);
        
            // PATCH /{subjectId}/toggle-compulsory
            Route::patch('/{subjectId}/toggle-compulsory', [ClassArmSubjectController::class, 'toggleCompulsory']);
        });
    });

    // Subjects
    Route::prefix('subjects')->group(function () {
        Route::post('/{id}/assign-teacher',               [SubjectController::class, 'assignTeacher']);
        Route::delete('/{id}/assignments/{assignmentId}', [SubjectController::class, 'removeTeacher']);
        Route::get('/',                                   [SubjectController::class, 'index']);
        Route::post('/',                                  [SubjectController::class, 'store']);
        Route::get('/{id}',                               [SubjectController::class, 'show']);
        Route::patch('/{id}',                             [SubjectController::class, 'update']);
        Route::delete('/{id}',                            [SubjectController::class, 'destroy']);
    });
    
    // Teachers
    Route::prefix('teachers')->group(function () {
        Route::post('/{id}/toggle-active',  [TeacherController::class, 'toggleActive']);
        Route::post('/{id}/reset-password', [TeacherController::class, 'resetPassword']);
        Route::post('/{id}/restore',        [TeacherController::class, 'restore']);
        Route::get('/',                     [TeacherController::class, 'index']);
        Route::post('/',                    [TeacherController::class, 'store']);
        Route::get('/{id}',                 [TeacherController::class, 'show']);
        Route::patch('/{id}',               [TeacherController::class, 'update']);
        Route::delete('/{id}',              [TeacherController::class, 'destroy']);
    });

    // Students
    Route::prefix('students')->group(function () {
        Route::post('/{id}/toggle-active',   [StudentController::class, 'toggleActive']);
        Route::post('/{id}/reassign-class',  [StudentController::class, 'reassignClass']);
        Route::post('/import',               [StudentImportController::class, 'import']);
        Route::get('/export',                [StudentController::class, 'exportCsv']);
        Route::post('/bulk-reset-passwords', [StudentController::class, 'bulkResetPasswords']);
        Route::get('/',                      [StudentController::class, 'index']);
        Route::post('/',                     [StudentController::class, 'store']);
        Route::get('/{id}',                  [StudentController::class, 'show']);
        Route::patch('/{id}',                [StudentController::class, 'update']);
    });

    // Grading scales
    Route::apiResource('grading-scales', GradingScaleController::class);

    // School settings
    Route::get('/school-settings',   [SchoolSettingController::class, 'index']);
    Route::patch('/school-settings', [SchoolSettingController::class, 'update']);
});