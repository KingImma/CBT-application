<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\StudentController;
use Illuminate\Support\Facades\Route;

// Static routes must be registered before apiResource to avoid capture by the `show` route
Route::get('students/export', [StudentController::class, 'exportCsv']);
Route::get('students/import-template', [StudentController::class, 'downloadImportTemplate']);
Route::post('students/import', [StudentController::class, 'importCsv'])->middleware(['permission:manage_students']);
Route::post('students/bulk-reset-passwords', [StudentController::class, 'bulkResetPasswords']);

Route::apiResource('students', StudentController::class);

Route::post('students/{id}/toggle-active', [StudentController::class, 'toggleActive']);
Route::post('students/{id}/reassign-class', [StudentController::class, 'reassignClass']);
Route::post('students/{id}/restore', [StudentController::class, 'restore']);
Route::post('students/{id}/revoke', [StudentController::class, 'revoke']);
