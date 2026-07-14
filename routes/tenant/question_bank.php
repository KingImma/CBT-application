<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\QuestionController;
use App\Http\Controllers\Api\Tenant\QuestionOptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Question Bank Resource
|--------------------------------------------------------------------------
*/

// ↓ Static routes MUST come before apiResource which registers questions/{question}
Route::controller(QuestionController::class)->group(function () {
    Route::post('questions/clone-from-term', 'cloneFromTerm');
    Route::post('questions/{id}/restore', 'restore');
    Route::apiResource('questions', QuestionController::class);
});

/*
|--------------------------------------------------------------------------
| Question Options Resource
|--------------------------------------------------------------------------
*/

Route::controller(QuestionOptionController::class)->group(function () {
    Route::prefix('questions/{questionId}/options')->group(function () {
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/reorder', 'reorder');
    });
});
