<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\StudentController;
use App\Http\Controllers\Api\Tenant\StudentExamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Students Resource
|--------------------------------------------------------------------------
*/

Route::controller(StudentController::class)->group(function () {

    // Exam results — student only (static route, kept ahead of wildcards)
    Route::get('students/results', [StudentExamController::class, 'results'])
        ->middleware(['role:student,tenant']);

    // Read routes — teachers and school_admins
    Route::middleware('role:teacher|school_admin,tenant')->group(function () {
        Route::get('students/export', 'exportCsv');
        Route::get('students/import-template', 'downloadImportTemplate');
        Route::get('students', 'index');
        Route::get('students/{student}', 'show'); // ← wildcard, must be last
    });

    // Mutation routes — school_admins only
    Route::middleware('role:school_admin,tenant')->group(function () {
        Route::post('students/import', 'importCsv')
            ->middleware(['permission:manage_students,tenant']);
        Route::post('students/bulk-reset-passwords', 'bulkResetPasswords');
        Route::post('students', 'store');
        Route::match(['put', 'patch'], 'students/{student}', 'update');
        Route::delete('students/{student}', 'destroy');

        Route::post('students/{id}/toggle-active', 'toggleActive');
        Route::post('students/{id}/reassign-class', 'reassignClass');
        Route::post('students/{id}/restore', 'restore');
        Route::post('students/{id}/revoke', 'revoke');
    });
});

/*
|--------------------------------------------------------------------------
| Student Exam Portal
|--------------------------------------------------------------------------
*/

Route::prefix('student/exams')
    ->middleware('role:student,tenant')
    ->controller(StudentExamController::class)
    ->group(function () {
        Route::get('/available', 'index');
        Route::get('/{id}', 'show')->whereUuid('id');
        Route::post('/{id}/start', 'start')->whereUuid('id');
        Route::get('/{id}/attempt', 'activeAttempt')->whereUuid('id');
        Route::get('/{id}/questions', 'getQuestions')->whereUuid('id');

        // Attempt sub-routes
        Route::prefix('/attempts/{id}')->whereUuid('id')->group(function () {
            Route::get('/questions', 'getAttemptQuestions');
            Route::get('/time-remaining', 'timeRemaining');
            Route::get('/result', 'result');
            Route::get('/session-state', 'sessionState');

            Route::put('/answers/{questionId}', 'saveAnswer')->whereUuid('questionId');
            Route::post('/bulk-save', 'bulkSave');
            Route::post('/submit', 'submit');
            Route::post('/flag/{questionId}', 'toggleFlag')->whereUuid('questionId');
            Route::post('/suspicious-event', 'logSuspiciousEvent')
                ->middleware('throttle:30,1');
        });
    });
