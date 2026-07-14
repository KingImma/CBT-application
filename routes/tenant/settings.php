<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\GradingScaleController;
use App\Http\Controllers\Api\Tenant\SchoolSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Grading Scales Resource
|--------------------------------------------------------------------------
*/

Route::apiResource('grading-scales', GradingScaleController::class)->middleware(
    ['permission:manage_grading_scales,tenant'],
);

/*
|--------------------------------------------------------------------------
| School Settings Resource
|--------------------------------------------------------------------------
*/

Route::prefix('school-settings')
    ->middleware(['role:teacher|school_admin,tenant'])
    ->controller(SchoolSettingController::class)
    ->group(function () {
        Route::get('/', 'index');

        Route::middleware(['permission:manage_school_settings,tenant'])->group(
            function () {
                Route::patch('/', 'update');
                Route::get('/assessments', 'assessments');
                Route::patch('/assessments', 'updateAssessments');
                Route::get('/assessment-defaults', 'assessmentDefaults');
                Route::patch(
                    '/assessment-defaults',
                    'updateAssessmentDefaults',
                );
            },
        );
    });
