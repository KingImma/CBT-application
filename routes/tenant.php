<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Tenant\TeacherController;
use App\Http\Controllers\Api\Tenant\NotificationController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)
    ->prefix('auth')
    ->group(function () {
        Route::post('/logout', 'logout');
        Route::get('/me', 'me');
    });


Route::middleware('auth:tenant')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/{id}/unread', [NotificationController::class, 'markUnread']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
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
