<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\SubjectController;
use Illuminate\Support\Facades\Route;

// View routes — teachers and school_admins
Route::get('subjects', [SubjectController::class, 'index'])->middleware('role:teacher|school_admin');
Route::get('subjects/{subject}', [SubjectController::class, 'show'])->middleware('role:teacher|school_admin');

// Mutation routes — school_admins only
Route::middleware('role:school_admin')->group(function () {
    Route::post('subjects', [SubjectController::class, 'store']);
    Route::match(['put', 'patch'], 'subjects/{subject}', [SubjectController::class, 'update']);
    Route::delete('subjects/{subject}', [SubjectController::class, 'destroy']);
    Route::post('subjects/{id}/assign-teacher', [SubjectController::class, 'assignTeacher']);
    Route::delete('subjects/{id}/assignments/{assignmentId}', [SubjectController::class, 'removeTeacher']);
});
