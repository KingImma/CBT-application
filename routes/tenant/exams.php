<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\ExamController;
use App\Http\Controllers\Api\Tenant\ExamGradingController;
use App\Http\Controllers\Api\Tenant\ExamQuestionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('exams', ExamController::class);

Route::prefix('exams/{id}')->controller(ExamController::class)->group(function () {
    Route::post('/submit-for-review', 'submitForReview');
    Route::post('/activate', 'activate');
    Route::post('/publish', 'publish');
    Route::post('/publish-results', 'publishResults');
    Route::post('/unpublish-results', 'unpublishResults');

    // School admin only
    Route::post('/force-complete', 'forceComplete')->middleware('role:school_admin,tenant');
});

Route::prefix('exams/{examId}/questions')
    ->controller(ExamQuestionController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/randomize', 'randomize');
        Route::patch('/{questionId}', 'update');
        Route::delete('/{questionId}', 'destroy');
        Route::post('/reorder', 'reorder');
    });

Route::prefix('exams/{id}/grading')
    ->controller(ExamGradingController::class)
    ->group(function () {
        Route::post('/attempts/{attemptId}/recompute-score', 'recomputeScore');
    });
