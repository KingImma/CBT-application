<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\QuestionController;
use App\Http\Controllers\Api\Tenant\QuestionOptionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('questions', QuestionController::class);
Route::post('questions/clone-from-term', [QuestionController::class, 'cloneFromTerm']);
Route::post('questions/{id}/restore', [QuestionController::class, 'restore']);

Route::prefix('questions/{questionId}/options')->controller(QuestionOptionController::class)->group(function () {
    Route::post('/', 'store');
    Route::patch('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
    Route::post('/reorder', 'reorder');
});
