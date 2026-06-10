<?php

// routes/tenant/classes.php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\ClassArmSubjectController;
use App\Http\Controllers\Api\Tenant\ClassLevelController;
use Illuminate\Support\Facades\Route;

// View routes — teachers and school_admins
Route::get('class-levels', [ClassLevelController::class, 'index'])->middleware('role:teacher|school_admin,tenant');
Route::get('class-levels/{classLevel}', [ClassLevelController::class, 'show'])->middleware('role:teacher|school_admin,tenant');
Route::get('class-levels/{classLevelId}/arms', [ClassArmController::class, 'index'])->middleware('role:teacher|school_admin,tenant');
Route::get('class-levels/{classLevelId}/subjects', [ClassLevelController::class, 'availableSubjects'])->middleware('role:teacher|school_admin,tenant');
Route::get('class-levels/{classLevelId}/arms/{armId}/subjects', [ClassArmSubjectController::class, 'index'])->middleware('role:teacher|school_admin,tenant');

// Mutation routes — school_admins only
Route::middleware('role:school_admin,tenant')->group(function () {
    Route::post('class-levels', [ClassLevelController::class, 'store']);
    Route::match(['put', 'patch'], 'class-levels/{classLevel}', [ClassLevelController::class, 'update']);
    Route::delete('class-levels/{classLevel}', [ClassLevelController::class, 'destroy']);
    Route::patch('class-levels/{id}/assign-teacher', [ClassLevelController::class, 'assignTeacher']);

    Route::post('class-levels/{classLevelId}/arms', [ClassArmController::class, 'store']);
    Route::patch('class-levels/{classLevelId}/arms/{id}/assign-teacher', [ClassArmController::class, 'assignTeacher']);
    Route::patch('class-levels/{classLevelId}/arms/{id}', [ClassArmController::class, 'update']);
    Route::delete('class-levels/{classLevelId}/arms/{id}', [ClassArmController::class, 'destroy']);

    Route::post('class-levels/{classLevelId}/subjects/sync', [ClassLevelController::class, 'sync']);
    Route::patch('class-levels/{classLevelId}/subjects/{subjectId}/toggle-compulsory', [ClassLevelController::class, 'toggleCompulsory']);

    Route::post('class-levels/{classLevelId}/arms/{armId}/subjects/sync', [ClassArmSubjectController::class, 'sync']);
    Route::post('class-levels/{classLevelId}/arms/{armId}/subjects/inherit', [ClassArmSubjectController::class, 'inheritFromLevel']);
    Route::post('class-levels/{classLevelId}/arms/{armId}/subjects/{subjectId}', [ClassArmSubjectController::class, 'attach']);
    Route::delete('class-levels/{classLevelId}/arms/{armId}/subjects/{subjectId}', [ClassArmSubjectController::class, 'detach']);
    Route::patch('class-levels/{classLevelId}/arms/{armId}/subjects/{subjectId}/toggle-compulsory', [ClassArmSubjectController::class, 'toggleCompulsory']);
});
