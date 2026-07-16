<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\ExamController;
use App\Http\Controllers\Api\Tenant\ExamGradingController;
use App\Http\Controllers\Api\Tenant\ExamQuestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Exams
|--------------------------------------------------------------------------
*/
Route::apiResource('exams', ExamController::class);

/*
|--------------------------------------------------------------------------
| Exam Actions
|--------------------------------------------------------------------------
*/
Route::prefix('exams')
    ->controller(ExamController::class)
    ->group(function () {
        Route::post('/{exam}/submit-for-review', 'submitForReview')
            ->middleware('role:school_admin|teacher,tenant');

        Route::middleware('role:school_admin,tenant')->group(function () {
            Route::post('/{exam}/activate', 'activate');
            Route::post('/{exam}/publish-results', 'publishResults');
            Route::post('/{exam}/unpublish-results', 'unpublishResults');
            Route::post('/{exam}/force-complete', 'forceComplete');
        });
    });
/*
|--------------------------------------------------------------------------
| Exam Questions
|--------------------------------------------------------------------------
*/
Route::controller(ExamQuestionController::class)
    ->prefix('exams/{exam}/questions')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/randomize', 'randomize');
        Route::patch('/{examQuestion}', 'update');
        Route::delete('/{examQuestion}', 'destroy');
        Route::post('/reorder', 'reorder');
    });

/*
|--------------------------------------------------------------------------
| Exam Grading
|--------------------------------------------------------------------------
*/
Route::controller(ExamGradingController::class)
    ->prefix('exams/{exam}/grading')
    ->middleware('role:teacher|school_admin,tenant')
    ->group(function () {
        Route::post('/attempts/{attempt}/recompute-score', 'recomputeScore');
        Route::get('/attempts/{attempt}/result', 'viewAttemptResult');
    });
