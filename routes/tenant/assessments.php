<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\AssessmentController;
use App\Http\Controllers\Api\Tenant\SubmissionController;
use App\Http\Controllers\Api\Tenant\ScheduleSubjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Assessments (admin-owned containers)
|--------------------------------------------------------------------------
| Read is open to teacher|school_admin (the SubmissionController lists papers
| against an assessment); writes + lifecycle are admin-only.
*/
Route::middleware('role:teacher|school_admin,tenant')->group(function () {
    Route::get('assessments', [AssessmentController::class, 'index']);
    Route::get('assessments/{assessment}', [AssessmentController::class, 'show']);
});

Route::middleware('role:school_admin,tenant')
    ->controller(AssessmentController::class)
    ->group(function () {
        Route::post('assessments', 'store');
        Route::put('assessments/{assessment}', 'update');
        Route::patch('assessments/{assessment}', 'update');
        Route::delete('assessments/{assessment}', 'destroy');

        Route::post('assessments/{assessment}/open', 'open');
        Route::post('assessments/{assessment}/close-submissions', 'closeSubmissions');
        Route::post('assessments/{assessment}/reopen', 'reopen');
        Route::post('assessments/{assessment}/activate', 'activate');
        Route::post('assessments/{assessment}/complete', 'complete');
    });


Route::middleware('role:school_admin,tenant')
    ->controller(ScheduleSubjectController::class)
    ->group(function () { 
        Route::get('assessments/{assessment}/schedule-subjects/', 'index');
        Route::post('assessments/{assessment}/schedule-subjects/', 'store');
        Route::patch('assessments/{assessment}/schedule-subjects/{scheduleSubject}', 'update');
        Route::delete('assessments/{assessment}/schedule-subjects/{scheduleSubject}', 'destroy');
    });

/*
|--------------------------------------------------------------------------
| Submissions (a teacher's paper inside an assessment)
|--------------------------------------------------------------------------
| Authoring is teacher|school_admin (policies enforce ownership + status);
| the review loop (request-changes / approve) is admin-only.
*/
Route::middleware('role:teacher|school_admin,tenant')
    ->controller(SubmissionController::class)
    ->group(function () {
        Route::get('assessments/{assessment}/submissions', 'index');
        Route::post('assessments/{assessment}/submissions', 'store');

        Route::get('submissions/{submission}', 'show');
        Route::put('submissions/{submission}', 'update');
        Route::patch('submissions/{submission}', 'update');

        Route::post('submissions/{submission}/questions', 'addQuestion');
        Route::delete('submissions/{submission}/questions/{question}', 'removeQuestion');

        Route::post('submissions/{submission}/submit-for-review', 'submitForReview');
    });

Route::middleware('role:school_admin,tenant')
    ->controller(SubmissionController::class)
    ->group(function () {
        Route::post('submissions/{submission}/request-changes', 'requestChanges');
        Route::post('submissions/{submission}/approve', 'approve');
    });
