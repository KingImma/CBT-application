<?php

use App\Http\Controllers\Api\Tenant\TeacherExamReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->group(function () {

    Route::get('class-arms/{classArm}/exams/{exam}', [TeacherExamReportController::class, 'examSummary']);

    Route::get('students/{student}/results', [TeacherExamReportController::class, 'studentResults']);

});
