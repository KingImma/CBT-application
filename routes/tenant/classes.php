<?php

// routes/tenant/classes.php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\ClassArmSubjectController;
use App\Http\Controllers\Api\Tenant\ClassLevelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Class Levels
|--------------------------------------------------------------------------
*/

Route::controller(ClassLevelController::class)
    ->middleware('role:teacher|school_admin,tenant')
    ->group(function () {
        Route::get('class-levels', 'index');
        Route::get('class-levels/{classLevel}', 'show');
        Route::get('class-levels/{classLevelId}/subjects', 'availableSubjects');
    });

/*
|--------------------------------------------------------------------------
| Class Arms
|--------------------------------------------------------------------------
*/

Route::controller(ClassArmController::class)
    ->middleware('role:teacher|school_admin,tenant')
    ->group(function () {
        Route::get('class-levels/{classLevelId}/arms', 'index');
    });

/*
|--------------------------------------------------------------------------
| Class Arm Subjects
|--------------------------------------------------------------------------
*/

Route::controller(ClassArmSubjectController::class)
    ->middleware('role:teacher|school_admin,tenant')
    ->group(function () {
        Route::get('class-levels/{classLevelId}/arms/{armId}/subjects', 'index');
    });

/*
|--------------------------------------------------------------------------
| Mutation Routes — school_admins only
|--------------------------------------------------------------------------
*/

Route::middleware('role:school_admin,tenant')->group(function () {

    // Class levels
    Route::controller(ClassLevelController::class)->group(function () {
        Route::post('class-levels', 'store');
        Route::match(['put', 'patch'], 'class-levels/{classLevel}', 'update');
        Route::delete('class-levels/{classLevel}', 'destroy');
        Route::patch('class-levels/{id}/assign-teacher', 'assignTeacher');

        Route::post('class-levels/{classLevelId}/subjects/sync', 'sync');
        Route::patch('class-levels/{classLevelId}/subjects/{subjectId}/toggle-compulsory', 'toggleCompulsory');
    });

    // Class arms
    Route::controller(ClassArmController::class)
        ->prefix('class-levels/{classLevelId}/arms')
        ->group(function () {
            Route::post('/', 'store');
            Route::patch('/{id}/assign-teacher', 'assignTeacher');
            Route::patch('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });

    // Class arm subjects
    Route::controller(ClassArmSubjectController::class)
        ->prefix('class-levels/{classLevelId}/arms/{armId}/subjects')
        ->group(function () {
            Route::post('/sync', 'sync');
            Route::post('/inherit', 'inheritFromLevel');
            Route::post('/{subjectId}', 'attach');
            Route::delete('/{subjectId}', 'detach');
            Route::patch('/{subjectId}/toggle-compulsory', 'toggleCompulsory');
        });
});
