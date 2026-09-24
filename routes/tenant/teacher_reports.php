<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\TeacherExamReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Teacher Reports Resource
|--------------------------------------------------------------------------
*/

Route::controller(TeacherExamReportController::class)->group(function () {

    Route::get('class-arms/{classArm}/exams/{exam}', 'examSummary')->name('exams.report');

    Route::get('students/{student}/results', 'studentResults');

    // Per-student cumulative result sheet
    Route::get('students/{studentId}/cumulative-result/pdf', 'cumulativeResultPdf');

    // Class-wide cumulative report: one result sheet per student in the arm
    Route::get('class-arms/{classArm}/exams/{exam}/report/pdf', 'classCumulativePdf')
        ->name('exams.report.cumulative-pdf');

     // Class-wide exam summary report (summary grid + roster)
    Route::get('class-arms/{classArm}/exams/{exam}/report/summary/pdf', 'examSummaryPdf')
        ->name('exams.report.summary-pdf');

    Route::get(
        'class-arms/{classArm}/exams/{exam}/results/bulk/pdf',
        'examResultsBulkPdf'
    )->name('exams.results.bulk-pdf');
});
