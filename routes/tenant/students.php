<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\StudentController;
use App\Http\Controllers\Api\Tenant\StudentExamController;
use Illuminate\Support\Facades\Route;

// View routes — teachers and school_admins
Route::middleware('role:teacher|school_admin,tenant')->group(function () {
    Route::get('students/export', [StudentController::class, 'exportCsv']);
    Route::get('students/import-template', [StudentController::class, 'downloadImportTemplate']);
    Route::get('students', [StudentController::class, 'index']);
    Route::get('students/{student}', [StudentController::class, 'show']);
});

// Mutation routes — school_admins only
Route::middleware('role:school_admin,tenant')->group(function () {
    Route::post('students/import', [StudentController::class, 'importCsv'])
        ->middleware(['permission:manage_students,tenant']);
    Route::post('students/bulk-reset-passwords', [StudentController::class, 'bulkResetPasswords']);
    Route::post('students', [StudentController::class, 'store']);
    Route::match(['put', 'patch'], 'students/{student}', [StudentController::class, 'update']);
    Route::delete('students/{student}', [StudentController::class, 'destroy']);
    Route::post('students/{id}/toggle-active', [StudentController::class, 'toggleActive']);
    Route::post('students/{id}/reassign-class', [StudentController::class, 'reassignClass']);
    Route::post('students/{id}/restore', [StudentController::class, 'restore']);
    Route::post('students/{id}/revoke', [StudentController::class, 'revoke']);
});

// Student self-service routes
Route::get('students/results', [StudentExamController::class, 'results'])->middleware(['role:student,tenant']);

Route::prefix('student/exams')->middleware('role:student,tenant')->controller(StudentExamController::class)->group(function () {
    Route::get('/available', 'index');
    Route::get('/{id}', 'show')->whereUuid('id');
    Route::post('/{id}/start', 'start')->whereUuid('id');
    Route::get('/{id}/attempt', 'activeAttempt')->whereUuid('id');
    Route::get('/{id}/questions', 'getQuestions')->whereUuid('id');
    Route::get('/attempts/{id}/questions', 'getAttemptQuestions')->whereUuid('id');
    Route::get('/attempts/{id}/time-remaining', 'timeRemaining')->whereUuid('id');
    Route::get('/attempts/{id}/result', 'result')->whereUuid('id');
    Route::put('/attempts/{id}/answers/{questionId}', 'saveAnswer')
        ->whereUuid('id')
        ->whereUuid('questionId');
    Route::post('/attempts/{id}/bulk-save', 'bulkSave')->whereUuid('id');
    Route::post('/attempts/{id}/submit', 'submit')->whereUuid('id');
    Route::post('/attempts/{id}/flag/{questionId}', 'toggleFlag')
        ->whereUuid('id')
        ->whereUuid('questionId');
    Route::post('/attempts/{id}/suspicious-event', 'logSuspiciousEvent')
        ->whereUuid('id')
        ->middleware('throttle:30,1');
});
