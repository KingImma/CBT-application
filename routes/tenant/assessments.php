<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\AssessmentController;
use App\Http\Controllers\Api\Tenant\AssessmentScheduleController;
use App\Http\Controllers\Api\Tenant\ScheduleSubjectController;
use App\Http\Controllers\Api\Tenant\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Assessments (admin-owned definitions)
|--------------------------------------------------------------------------
| A definition holds no dates or lifecycle. Occurrences live on
| assessment-schedules. Read is open to teacher|school_admin; writes are
| admin-only.
*/
Route::middleware('role:teacher|school_admin,tenant')->group(function () {
    Route::get('assessments', [AssessmentController::class, 'index']);
    Route::get('assessments/{assessment}', [AssessmentController::class, 'show']);

    // The reuse view: every occurrence of an assessment, per session/term.
    Route::get('assessments/{assessment}/schedules', [AssessmentScheduleController::class, 'index']);
});

Route::middleware('role:school_admin,tenant')
    ->controller(AssessmentController::class)
    ->group(function () {
        Route::post('assessments', 'store');
        Route::put('assessments/{assessment}', 'update');
        Route::patch('assessments/{assessment}', 'update');
        Route::delete('assessments/{assessment}', 'destroy');
    });

/*
|--------------------------------------------------------------------------
| Assessment schedules (occurrences) — shallow resource, admin-owned writes
|--------------------------------------------------------------------------
| Creating a schedule immediately opens its teacher question window. The
| student exam phase runs draft -> active -> completed.
*/
Route::middleware('role:teacher|school_admin,tenant')->group(function () {
    Route::get('assessment-schedules/{schedule}', [AssessmentScheduleController::class, 'show']);
});

Route::middleware('role:school_admin,tenant')
    ->controller(AssessmentScheduleController::class)
    ->group(function () {
        Route::post('assessments/{assessment}/schedules', 'store');

        Route::put('assessment-schedules/{schedule}', 'update');
        Route::patch('assessment-schedules/{schedule}', 'update');
        Route::delete('assessment-schedules/{schedule}', 'destroy');

        Route::post('assessment-schedules/{schedule}/close-submissions', 'closeSubmissions');
        Route::post('assessment-schedules/{schedule}/reopen', 'reopen');
        Route::post('assessment-schedules/{schedule}/activate', 'activate');
        Route::post('assessment-schedules/{schedule}/complete', 'complete');
    });

/*
|--------------------------------------------------------------------------
| Subject slots (per-subject exam windows inside a schedule's master window)
|--------------------------------------------------------------------------
*/
Route::middleware('role:teacher|school_admin,tenant')->group(function () {
    Route::get('assessment-schedules/{schedule}/schedule-subjects', [ScheduleSubjectController::class, 'index']);
});

Route::middleware('role:school_admin,tenant')
    ->controller(ScheduleSubjectController::class)
    ->prefix('assessment-schedules/{schedule}/schedule-subjects')
    ->group(function () {
        Route::post('/', 'store');
        Route::patch('/{scheduleSubject}', 'update');
        Route::delete('/{scheduleSubject}', 'destroy');
    });

/*
|--------------------------------------------------------------------------
| Teacher submissions (a teacher's paper inside a schedule occurrence)
|--------------------------------------------------------------------------
| Authoring is teacher|school_admin (policies enforce ownership + status);
| the review loop (request-changes / approve) is admin-only.
*/
Route::middleware('role:teacher|school_admin,tenant')->group(function () {
    Route::get('assessment-schedules/{schedule}/submissions', [SubmissionController::class, 'index']);
    Route::post('assessment-schedules/{schedule}/submissions', [SubmissionController::class, 'store']);

    Route::get('submissions/{submission}', [SubmissionController::class, 'show']);
    Route::put('submissions/{submission}', [SubmissionController::class, 'update']);
    Route::patch('submissions/{submission}', [SubmissionController::class, 'update']);

    Route::post('submissions/{submission}/questions', [SubmissionController::class, 'addQuestion']);
    Route::delete('submissions/{submission}/questions/{question}', [SubmissionController::class, 'removeQuestion']);

    Route::post('submissions/{submission}/submit-for-review', [SubmissionController::class, 'submitForReview']);
});

Route::middleware('role:school_admin,tenant')
    ->controller(SubmissionController::class)
    ->group(function () {
        Route::post('submissions/{submission}/request-changes', 'requestChanges');
        Route::post('submissions/{submission}/approve', 'approve');
    });
