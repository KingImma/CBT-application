<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\GradingScaleController;
use App\Http\Controllers\Api\Tenant\SchoolSettingController;
use Illuminate\Support\Facades\Route;

Route::apiResource('grading-scales', GradingScaleController::class)->middleware(['permission:manage_grading_scales']);

Route::prefix('school-settings')->middleware(['role:teacher|school_admin'])->controller(SchoolSettingController::class)->group(function () {
    Route::get('/', 'index');
    Route::patch('/', 'update');

    Route::middleware(['permission:manage_school_settings'])->group(function () {
        Route::get('/assessments', 'assessments');
        Route::patch('/assessments', 'updateAssessments');
        Route::get('/assessment-defaults', 'assessmentDefaults');
        Route::patch('/assessment-defaults', 'updateAssessmentDefaults');
    });
});
