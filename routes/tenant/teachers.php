<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\TeacherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Teachers Resource
|--------------------------------------------------------------------------
*/

Route::controller(TeacherController::class)->group(function () {

    // Mutation routes — school_admins only
    Route::middleware('role:school_admin,tenant')->group(function () {
        // Static routes must be registered before apiResource to avoid capture by the `show` route
        Route::get('teachers/import-template', 'downloadImportTemplate');
        Route::post('teachers/import', 'importCsv')->middleware(['permission:manage_teachers,tenant']);

        Route::get('teachers/{id}/classes', 'classes');
        Route::get('teachers/{id}/subjects', 'subjects');
        Route::post('teachers/{id}/toggle-active', 'toggleActive');
        Route::post('teachers/{id}/reset-password', 'resetPassword');
        Route::post('teachers/{id}/restore', 'restore');
        Route::post('teachers/{id}/revoke', 'revoke');
    });

    // Read routes — teachers and school_admins
    Route::middleware('role:teacher|school_admin,tenant')->group(function () {
        Route::apiResource('teachers', TeacherController::class);
    });
});
