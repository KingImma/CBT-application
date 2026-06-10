<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\TermController;
use Illuminate\Support\Facades\Route;

// View routes — teachers and school_admins
Route::get('academic-sessions', [AcademicSessionController::class, 'index'])->middleware('role:teacher|school_admin');
Route::get('academic-sessions/{academicSession}', [AcademicSessionController::class, 'show'])->middleware('role:teacher|school_admin');
Route::get('academic-sessions/{sessionId}/terms', [TermController::class, 'index'])->middleware('role:teacher|school_admin');

// Mutation routes — school_admins only
Route::middleware('role:school_admin')->group(function () {
    Route::post('academic-sessions', [AcademicSessionController::class, 'store']);
    Route::match(['put', 'patch'], 'academic-sessions/{academicSession}', [AcademicSessionController::class, 'update']);
    Route::delete('academic-sessions/{academicSession}', [AcademicSessionController::class, 'destroy']);
    Route::post('academic-sessions/{id}/set-current', [AcademicSessionController::class, 'setCurrent']);

    Route::post('academic-sessions/{sessionId}/terms', [TermController::class, 'store']);
    Route::match(['put', 'patch'], 'academic-sessions/{sessionId}/terms/{term}', [TermController::class, 'update']);
    Route::delete('academic-sessions/{sessionId}/terms/{term}', [TermController::class, 'destroy']);
    Route::post('academic-sessions/{sessionId}/terms/{id}/set-current', [TermController::class, 'setCurrent']);
});
