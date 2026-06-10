<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\StudentController;
use App\Http\Controllers\Api\Tenant\StudentExamController;
use Illuminate\Support\Facades\Route;

// ⚠️ Register before apiResource to avoid capture by `show` route
Route::get('students/export', [StudentController::class, 'exportCsv']);
Route::get('students/import-template', [StudentController::class, 'downloadImportTemplate']);
Route::post('students/import', [StudentController::class, 'importCsv'])
    ->middleware(['permission:manage_students']);
Route::post('students/bulk-reset-passwords', [StudentController::class, 'bulkResetPasswords']);

Route::apiResource('students', StudentController::class);

Route::post('students/{id}/toggle-active', [StudentController::class, 'toggleActive']);
Route::post('students/{id}/reassign-class', [StudentController::class, 'reassignClass']);
Route::post('students/{id}/restore', [StudentController::class, 'restore']);
Route::post('students/{id}/revoke', [StudentController::class, 'revoke']);
Route::get('students/results', [StudentExamController::class, 'results'])->middleware(['role:student']);

Route::prefix('student/exams')->controller(StudentExamController::class)->group(function () {
    // Discovery
    Route::get('/available', 'index');
    Route::get('/{id}', 'show')->whereUuid('id');

    // Lifecycle
    Route::post('/{id}/start', 'start')->whereUuid('id');
    Route::get('/{id}/attempt', 'activeAttempt')->whereUuid('id');

    // Content
    Route::get('/{id}/questions', 'getQuestions')->whereUuid('id');

    // Attempts
    Route::get('/attempts/{id}/questions', 'getAttemptQuestions')->whereUuid('id');
    Route::get('/attempts/{id}/time-remaining', 'timeRemaining')->whereUuid('id');
    Route::get('/attempts/{id}/result', 'result')->whereUuid('id');

    // Answers
    Route::put('/attempts/{id}/answers/{questionId}', 'saveAnswer')
        ->whereUuid('id')
        ->whereUuid('questionId');
    Route::post('/attempts/{id}/bulk-save', 'bulkSave')->whereUuid('id');
    Route::post('/attempts/{id}/submit', 'submit')->whereUuid('id');

    // Integrity
    Route::post('/attempts/{id}/flag/{questionId}', 'toggleFlag')
        ->whereUuid('id')
        ->whereUuid('questionId');
    Route::post('/attempts/{id}/suspicious-event', 'logSuspiciousEvent')
        ->whereUuid('id')
        ->middleware('throttle:30,1');
});