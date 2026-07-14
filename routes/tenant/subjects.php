<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\SubjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Subjects Resource
|--------------------------------------------------------------------------
*/

Route::controller(SubjectController::class)->group(function () {
    // Read routes — teachers and school_admins
    Route::get('subjects', 'index')->middleware('role:teacher|school_admin,tenant');
    Route::get('subjects/{subject}', 'show')->middleware('role:teacher|school_admin,tenant');

    // Mutation routes — school_admins only
    Route::middleware('role:school_admin,tenant')->group(function () {
        Route::post('subjects', 'store');
        Route::match(['put', 'patch'], 'subjects/{subject}', 'update');
        Route::delete('subjects/{subject}', 'destroy');
        Route::post('subjects/{id}/assign-teacher', 'assignTeacher');
        Route::delete('subjects/{id}/assignments/{assignmentId}', 'removeTeacher');
    });
});
