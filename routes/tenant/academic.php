<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\TermController;
use Illuminate\Support\Facades\Route;

Route::apiResource('academic-sessions', AcademicSessionController::class);
Route::post('academic-sessions/{id}/set-current', [AcademicSessionController::class, 'setCurrent']);

Route::apiResource('academic-sessions.terms', TermController::class)->except(['show']);
Route::post('academic-sessions/{sessionId}/terms/{id}/set-current', [TermController::class, 'setCurrent']);
