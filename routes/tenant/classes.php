<?php

// routes/tenant/classes.php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\ClassArmSubjectController;
use App\Http\Controllers\Api\Tenant\ClassLevelController;
use Illuminate\Support\Facades\Route;

Route::patch('class-levels/{id}/assign-teachers', [ClassLevelController::class, 'assignTeachers']);

Route::apiResource('class-levels', ClassLevelController::class);

Route::prefix('class-levels/{classLevelId}/arms')->controller(ClassArmController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::patch('/{id}/assign-teacher', 'assignTeacher');
    Route::patch('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
});

Route::prefix('class-levels/{classLevelId}/subjects')->controller(ClassLevelController::class)->group(function () {
    Route::get('/', 'availableSubjects');
    Route::post('/sync', 'sync');
    Route::patch('/{subjectId}/toggle-compulsory', 'toggleCompulsory');
});

Route::prefix('class-levels/{classLevelId}/arms/{armId}/subjects')->controller(ClassArmSubjectController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/sync', 'sync');
    Route::post('/inherit', 'inheritFromLevel');
    Route::post('/{subjectId}', 'attach');
    Route::delete('/{subjectId}', 'detach');
    Route::patch('/{subjectId}/toggle-compulsory', 'toggleCompulsory');
});
