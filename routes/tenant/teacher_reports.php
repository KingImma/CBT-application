<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\TeacherExamReportController;
use Illuminate\Support\Facades\Route;

Route::get(
    'class-arms/{armId}/exams/{exam}/report',
    [TeacherExamReportController::class, 'show']
)->middleware('permission:view_results,tenant');
