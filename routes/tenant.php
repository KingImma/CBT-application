<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Tenant\TeacherController;
use Illuminate\Support\Facades\Route;

// Auth
Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('/logout', 'logout');
    Route::get('/me', 'me');
});

Route::post('/teachers/{id}/reset-password-otp', [TeacherController::class, 'resetPassword']);

require __DIR__.'/tenant/academic.php';
require __DIR__.'/tenant/classes.php';
require __DIR__.'/tenant/subjects.php';
require __DIR__.'/tenant/teachers.php';
require __DIR__.'/tenant/students.php';

require __DIR__.'/tenant/settings.php';

Route::middleware(['role:teacher|school_admin'])->group(function () {
    require __DIR__.'/tenant/exams.php';
    require __DIR__.'/tenant/question_bank.php';
});

Route::middleware(['role:student'])->group(function () {
    require __DIR__.'/tenant/student_portal.php';
});
