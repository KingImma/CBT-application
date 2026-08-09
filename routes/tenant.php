<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Tenant\TeacherController;
use App\Http\Controllers\Api\Tenant\NotificationController;
use App\Http\Controllers\Api\Tenant\ExamReviewController;
use App\Http\Controllers\Api\Tenant\TeacherExamReviewController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)
    ->prefix('auth')
    ->group(function () {
        Route::post('/logout', 'logout');
        Route::get('/me', 'me');
    });


Route::controller(NotificationController::class)
    ->prefix('notifications')
    ->middleware('auth:tenant')->group(function () {
    Route::get('/', 'index');
    Route::get('/unread-count', 'unreadCount');
    Route::patch('/{id}/read', 'markRead');
    Route::patch('/{id}/unread', 'markUnread');
    Route::patch('/read-all', 'markAllRead');
    Route::delete('/{id}', 'destroy');
});

Route::controller(ExamReviewController::class)
    ->prefix('exams/{exam}/review')
    ->middleware(['auth:tenant', 'role:school_admin'])->group(function () {
        Route::get('/', 'show');
        Route::post('/comments', 'addComment');
    });

// Teacher side of the review thread: view admin comments + reply to them.
// role:teacher keeps admins out (the view policy would otherwise pass for them),
// then the `view` policy enforces exam ownership.
Route::controller(TeacherExamReviewController::class)
    ->prefix('exams/{exam}/review')
    ->middleware(['auth:tenant', 'role:teacher'])->group(function () {
        Route::get('/thread', 'show');
        Route::post('/comments/{commentId}/replies', 'replyToComment');
    });

Route::post('/teachers/{id}/reset-password-otp', [
    TeacherController::class,
    'resetPassword',
])->middleware('role:school_admin,tenant');

require __DIR__.'/tenant/academic.php';
require __DIR__.'/tenant/classes.php';
require __DIR__.'/tenant/subjects.php';

Route::middleware('role:school_admin,tenant')->group(function () {
    require __DIR__.'/tenant/teachers.php';
});

require __DIR__.'/tenant/students.php';

require __DIR__.'/tenant/settings.php';

Route::middleware(['role:teacher|school_admin,tenant'])->group(function () {
    require __DIR__.'/tenant/exams.php';
    require __DIR__.'/tenant/question_bank.php';
    require __DIR__.'/tenant/teacher_reports.php';
});
