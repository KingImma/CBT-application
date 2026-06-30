<?php

use App\Http\Controllers\Api\Tenant\TeacherExamReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->group(function () {

    // Exam-level reports (Class summaries, grading statistics)
    Route::get('exams/{exam}', [TeacherExamReportController::class, 'examSummary']);

    // Student-level reports (Viewing a specific student's attempt history)
    Route::get('students/{student}/results', [TeacherExamReportController::class, 'studentResults']);

});
