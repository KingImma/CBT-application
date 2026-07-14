<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\TermController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| View Routes — teachers and school_admins
|--------------------------------------------------------------------------
*/

Route::middleware('role:teacher|school_admin,tenant')->group(function () {
    Route::controller(AcademicSessionController::class)->group(function () {
        Route::get('academic-sessions', 'index');
        Route::get('academic-sessions/{academicSession}', 'show');
    });

    Route::get('academic-sessions/{sessionId}/terms', [TermController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Mutation Routes — school_admins only
|--------------------------------------------------------------------------
*/

Route::middleware('role:school_admin,tenant')->group(function () {

    Route::controller(AcademicSessionController::class)->group(function () {
        Route::post('academic-sessions', 'store');
        Route::match(['put', 'patch'], 'academic-sessions/{academicSession}', 'update');
        Route::delete('academic-sessions/{academicSession}', 'destroy');
        Route::post('academic-sessions/{id}/set-current', 'setCurrent');
    });

    Route::controller(TermController::class)
        ->prefix('academic-sessions/{sessionId}/terms')
        ->group(function () {
            Route::post('/', 'store');
            Route::match(['put', 'patch'], '/{term}', 'update');
            Route::delete('/{term}', 'destroy');
            Route::post('/{id}/set-current', 'setCurrent');
        });
});
