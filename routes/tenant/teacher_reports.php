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

    Route::get('students/{studentId}/cumulative-result/pdf', 'cumulativeResultPdf');

    Route::get('class-arms/{classArm}/exams/{exam}/report/pdf', 'examSummaryPdf')->name('exams.report.cumulative-pdf');

    Route::get(
        'class-arms/{classArm}/exams/{exam}/results/bulk/pdf',
        'examResultsBulkPdf'
    )->name('exams.results.bulk-pdf');
});
