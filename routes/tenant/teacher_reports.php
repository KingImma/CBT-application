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

});
