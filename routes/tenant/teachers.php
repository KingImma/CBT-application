<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\TeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:school_admin')->group(function () {
    // Static routes must be registered before apiResource to avoid capture by the `show` route
    Route::get('teachers/import-template', [TeacherController::class, 'downloadImportTemplate']);
    Route::post('teachers/import', [TeacherController::class, 'importCsv'])->middleware(['permission:manage_teachers,tenant']);

    Route::apiResource('teachers', TeacherController::class);

    Route::get('teachers/{id}/classes', [TeacherController::class, 'classes']);
    Route::get('teachers/{id}/subjects', [TeacherController::class, 'subjects']);
    Route::post('teachers/{id}/toggle-active', [TeacherController::class, 'toggleActive']);
    Route::post('teachers/{id}/reset-password', [TeacherController::class, 'resetPassword']);
    Route::post('teachers/{id}/restore', [TeacherController::class, 'restore']);
    Route::post('teachers/{id}/revoke', [TeacherController::class, 'revoke']);
});
