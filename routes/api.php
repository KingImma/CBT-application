<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

// ── Imports: Super Admin ──────────────────────────────────────────────────────
use App\Http\Controllers\Api\SuperAdmin\AuthController as SuperAdminAuthController;
use App\Http\Controllers\Api\SuperAdmin\AnalyticsController;
use App\Http\Controllers\Api\SuperAdmin\TenantController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;

// ── Imports: Tenant ───────────────────────────────────────────────────────────
use App\Http\Controllers\Api\Tenant\AuthController as TenantAuthController;
use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\TermController;
use App\Http\Controllers\Api\Tenant\PasswordController;
use App\Http\Controllers\Api\Tenant\StudentImportController;
use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\SubjectController;
use App\Http\Controllers\Api\Tenant\GradingScaleController;
use App\Http\Controllers\Api\Tenant\SchoolSettingController;
use App\Http\Controllers\Api\Tenant\TeacherController;
use App\Http\Controllers\Api\Tenant\StudentController;
// Note: Ensure TeacherController and StudentController are imported at the top of your actual file
use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\EnsureTenantAuthenticated;

/*
|--------------------------------------------------------------------------
| 1. GLOBAL PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
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
        'env_value'      => env('CENTRAL_DOMAIN'),
        'app_config'     => config('app.central_domain'),
        'tenancy_config' => config('tenancy.central_domains'),
    ]);
});

Route::get('/plans', [SubscriptionPlanController::class, 'index']);
Route::get('/plans/{id}', [SubscriptionPlanController::class, 'show']);

/*
|--------------------------------------------------------------------------
| 2. SUPER ADMIN ROUTES (Central Database)
|--------------------------------------------------------------------------
*/
Route::prefix('super-admin')->group(function () {
    Route::post('login', [SuperAdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', EnsureUserIsSuperAdmin::class])->group(function () {
        Route::post('logout', [SuperAdminAuthController::class, 'logout']);
        Route::get('me', [SuperAdminAuthController::class, 'me']);
        
        // Analytics & Logs
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
        Route::get('/analytics/usage',    [AnalyticsController::class, 'usage']);
        Route::get('/audit-logs',         [AnalyticsController::class, 'auditLogs']);
        
        // Subscription Plans
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

/*
|--------------------------------------------------------------------------
| 3. TENANT "SMART" AUTH ROUTES (No X-Tenant Header Required)
|--------------------------------------------------------------------------
| These routes dynamically resolve the tenant based on the user's email.
*/
Route::prefix('auth')->group(function () {
    Route::post('/login',           [TenantAuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);
    Route::post('/reset-password',  [PasswordController::class, 'resetPassword']);
});

// CSV template — no auth needed so admin can share link freely
Route::get('/students/import/template', [StudentImportController::class, 'downloadTemplate']);


/*
|--------------------------------------------------------------------------
| 4. PROTECTED TENANT ROUTES (X-Tenant Header REQUIRED)
|--------------------------------------------------------------------------
| Every route inside this block requires the `X-Tenant` header to route 
| the database connection, AND the Sanctum Bearer token for auth.
*/
Route::middleware([
    InitializeTenancyByHeader::class, 
    EnsureTenantAuthenticated::class
])->group(function () {

    Route::post('/auth/logout',          [TenantAuthController::class, 'logout']);
    Route::get('/auth/me',               [TenantAuthController::class, 'me']);
    Route::post('/auth/change-password', [PasswordController::class, 'change']);

    // Academic sessions
    Route::prefix('academic-sessions')->group(function () {
        Route::get('/',                      [AcademicSessionController::class, 'index']);
        Route::post('/',                     [AcademicSessionController::class, 'store']);
        Route::get('/{id}',                  [AcademicSessionController::class, 'show']);
        Route::patch('/{id}',                [AcademicSessionController::class, 'update']);
        Route::delete('/{id}',               [AcademicSessionController::class, 'destroy']);
        Route::post('/{id}/set-current',     [AcademicSessionController::class, 'setCurrent']);

        // Terms nested under sessions
        Route::prefix('/{sessionId}/terms')->group(function () {
            Route::get('/',                  [TermController::class, 'index']);
            Route::post('/',                 [TermController::class, 'store']);
            Route::patch('/{id}',            [TermController::class, 'update']);
            Route::delete('/{id}',           [TermController::class, 'destroy']);
            Route::post('/{id}/set-current', [TermController::class, 'setCurrent']);
        });
    });

    // Class levels
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
        Route::get('/',                                   [SubjectController::class, 'index']);
        Route::post('/',                                  [SubjectController::class, 'store']);
        Route::get('/{id}',                               [SubjectController::class, 'show']);
        Route::patch('/{id}',                             [SubjectController::class, 'update']);
        Route::delete('/{id}',                            [SubjectController::class, 'destroy']);
        Route::post('/{id}/assign-teacher',               [SubjectController::class, 'assignTeacher']);
        Route::delete('/{id}/assignments/{assignmentId}', [SubjectController::class, 'removeTeacher']);
    });
    
    // Teachers
    Route::prefix('teachers')->group(function () {
        // Assuming you have a TeacherController
        Route::get('/',                     [TeacherController::class, 'index']);
        Route::post('/',                    [TeacherController::class, 'store']);
        Route::get('/{id}',                 [TeacherController::class, 'show']);
        Route::patch('/{id}',               [TeacherController::class, 'update']);
        Route::post('/{id}/toggle-active',  [TeacherController::class, 'toggleActive']);
        Route::post('/{id}/reset-password', [TeacherController::class, 'resetPassword']);
    });

    // Students
    Route::prefix('students')->group(function () {
        // Assuming you have a StudentController
        Route::get('/',                      [StudentController::class, 'index']);
        Route::post('/',                     [StudentController::class, 'store']);
        Route::get('/export',                [StudentController::class, 'exportCsv']);
        Route::post('/bulk-reset-passwords', [StudentController::class, 'bulkResetPasswords']);
        Route::post('/import',               [StudentImportController::class, 'import']);
        Route::get('/{id}',                  [StudentController::class, 'show']);
        Route::patch('/{id}',                [StudentController::class, 'update']);
        Route::post('/{id}/toggle-active',   [StudentController::class, 'toggleActive']);
        Route::post('/{id}/reassign-class',  [StudentController::class, 'reassignClass']);
    });

    // Grading scales
    Route::prefix('grading-scales')->group(function () {
        Route::get('/',         [GradingScaleController::class, 'index']);
        Route::post('/',        [GradingScaleController::class, 'store']);
        Route::patch('/{id}',   [GradingScaleController::class, 'update']);
        Route::delete('/{id}',  [GradingScaleController::class, 'destroy']);
    });

    // School settings
    Route::get('/school-settings',   [SchoolSettingController::class, 'index']);
    Route::patch('/school-settings', [SchoolSettingController::class, 'update']);
});