<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\ExamAttendanceController;
use App\Http\Controllers\Api\Tenant\ExamController;
use App\Http\Controllers\Api\Tenant\ExamGradingController;
use App\Http\Controllers\Api\Tenant\ExamMonitoringController;
use App\Http\Controllers\Api\Tenant\ExamQuestionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('exams', ExamController::class);
Route::post('exams/{id}/submit-for-review', [ExamController::class, 'submitForReview']);
Route::post('exams/{id}/activate', [ExamController::class, 'activate']);
Route::post('exams/{id}/lock', [ExamController::class, 'lock']);
Route::post('exams/{id}/publish', [ExamController::class, 'publish']);
Route::post('exams/{id}/start-session', [ExamController::class, 'startSession']);
Route::post('exams/{id}/end-session', [ExamController::class, 'endSession']);

Route::prefix('exams/{examId}/questions')->controller(ExamQuestionController::class)->group(function () {
    Route::post('/', 'store');
    Route::post('/randomize', 'randomize');
    Route::patch('/{questionId}', 'update');
    Route::delete('/{questionId}', 'destroy');
    Route::post('/reorder', 'reorder');
});

Route::prefix('exams/{id}/attendance')->controller(ExamAttendanceController::class)->group(function () {
    Route::get('/class-students', 'classStudents');
    Route::post('/batch', 'batchStore');
    Route::put('/{studentId}', 'update');
});

Route::prefix('exams/{id}/monitor')->controller(ExamMonitoringController::class)->group(function () {
    Route::get('/', 'index');
});

Route::prefix('exams/{id}/grading')->controller(ExamGradingController::class)->group(function () {
    Route::post('/attempts/{attemptId}/recompute-score', 'recomputeScore');
});
