<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\StudentExamController;
use Illuminate\Support\Facades\Route;

Route::prefix('student/exams')->controller(StudentExamController::class)->group(function () {
    Route::get('/available', 'index');
    Route::get('/{id}', 'show');
    Route::post('/{id}/start', 'start');
    Route::get('/{id}/attempt', 'activeAttempt');
    Route::get('/{id}/questions', 'getQuestions');
    Route::get('/attempts/{id}/questions', 'getAttemptQuestions');
    Route::put('/attempts/{id}/answers/{questionId}', 'saveAnswer');
    Route::post('/attempts/{id}/bulk-save', 'bulkSave');
    Route::get('/attempts/{id}/time-remaining', 'timeRemaining');
    Route::post('/attempts/{id}/submit', 'submit');
    Route::post('/attempts/{id}/flag/{questionId}', 'toggleFlag');
    Route::post('/attempts/{id}/suspicious-event', 'logSuspiciousEvent')->middleware('throttle:30,1');
    Route::get('/attempts/{id}/result', 'result');
});
