<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\SubjectController;
use Illuminate\Support\Facades\Route;

Route::apiResource('subjects', SubjectController::class);
Route::post('subjects/{id}/assign-teacher', [SubjectController::class, 'assignTeacher']);
Route::delete('subjects/{id}/assignments/{assignmentId}', [SubjectController::class, 'removeTeacher']);
