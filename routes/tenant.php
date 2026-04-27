<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\TermController;
use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\ClassLevelController;
use App\Http\Controllers\Api\Tenant\SubjectController;
use App\Http\Controllers\Api\Tenant\GradingScaleController;
use App\Http\Controllers\Api\Tenant\SchoolSettingController;
use App\Http\Controllers\Api\Tenant\TeacherController;
use App\Http\Controllers\Api\Tenant\StudentController;
use App\Http\Controllers\Api\Tenant\ClassArmSubjectController;


/*
 * The core operating routes for an individual school.
 * Expected deliverables: A highly maintainable map of your core business features. 
 */

// Auth & Passwords
Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('/logout', 'logout');
    Route::get('/me', 'me');
});
Route::post('/auth/change-password', [\App\Http\Controllers\Api\Tenant\PasswordController::class, 'change']);

// Academic Sessions & Terms
Route::prefix('academic-sessions')->controller(AcademicSessionController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/set-current', 'setCurrent');

    Route::prefix('/{sessionId}/terms')->controller(TermController::class)->group(function () {
        Route::post('/{id}/set-current', 'setCurrent');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
});

// Class levels & Arms
Route::prefix('class-levels')->controller(ClassLevelController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');

    Route::prefix('/{id}/subjects')->controller(ClassLevelController::class)->group(function () {
        Route::get('/', 'availableSubjects');
        Route::post('/sync', 'sync');
        Route::patch('/{subjectId}/toggle-compulsory', 'toggleCompulsory');
    });

    Route::prefix('/{classLevelId}/arms')->controller(ClassArmController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
    
    Route::prefix('/{classLevelId}/arms/{armId}/subjects')->controller(ClassArmSubjectController::class)->group(function () {
        // GET  — list assigned + unassigned subjects for this arm
        Route::get('/', 'index');
    
        // POST /sync — replace entire subject list atomically
        Route::post('/sync', 'sync');
    
        // POST /inherit — copy all class-level subjects to this arm
        Route::post('/inherit', 'inheritFromLevel');
    
        // POST /{subjectId} — add one subject
        Route::post('/{subjectId}', 'attach');
    
        // DELETE /{subjectId} — remove one subject
        Route::delete('/{subjectId}', 'detach');
    
        // PATCH /{subjectId}/toggle-compulsory
        Route::patch('/{subjectId}/toggle-compulsory', 'toggleCompulsory');
    });
});

// Subjects
Route::prefix('subjects')->controller(SubjectController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
    // custom
    Route::post('/{id}/assign-teacher', 'assignTeacher');
    Route::delete('/{id}/assignments/{assignmentId}', 'removeTeacher');
});

// Teachers
Route::prefix('teachers')->controller(TeacherController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
    // custom
    Route::post('/{id}/toggle-active', 'toggleActive');
    Route::post('/{id}/reset-password', 'resetPassword');
    Route::post('/{id}/restore', 'restore');
});

// Students
Route::prefix('students')->controller(StudentController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    // custom-links
    Route::post('/{id}/toggle-active', 'toggleActive');
    Route::post('/{id}/reassign-class', 'reassignClass');
    Route::get('/export', 'exportCsv');
    Route::get('/import-template', 'downloadImportTemplate');
    Route::post('/import', 'importCsv');
    Route::post('/bulk-reset-passwords', 'bulkResetPasswords');
});

// Grading scales
Route::apiResource('grading-scales', GradingScaleController::class);

// School settings
Route::prefix('school-settings')->controller(SchoolSettingController::class)->group(function () {
    Route::get('/', 'index');
    Route::patch('/', 'update');
});