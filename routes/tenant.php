<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Tenant\AcademicSessionController;
use App\Http\Controllers\Api\Tenant\ClassArmController;
use App\Http\Controllers\Api\Tenant\ClassArmSubjectController;
use App\Http\Controllers\Api\Tenant\ClassLevelController;
use App\Http\Controllers\Api\Tenant\ExamAttendanceController;
use App\Http\Controllers\Api\Tenant\ExamController;
use App\Http\Controllers\Api\Tenant\ExamGradingController;
use App\Http\Controllers\Api\Tenant\ExamMonitoringController;
use App\Http\Controllers\Api\Tenant\ExamQuestionController;
use App\Http\Controllers\Api\Tenant\GradingScaleController;
use App\Http\Controllers\Api\Tenant\QuestionController;
use App\Http\Controllers\Api\Tenant\QuestionOptionController;
use App\Http\Controllers\Api\Tenant\SchoolSettingController;
use App\Http\Controllers\Api\Tenant\StudentController;
use App\Http\Controllers\Api\Tenant\StudentExamController;
use App\Http\Controllers\Api\Tenant\SubjectController;
use App\Http\Controllers\Api\Tenant\TeacherController;
use App\Http\Controllers\Api\Tenant\TermController;
use App\Http\Controllers\Api\Tenant\TopicController;
use Illuminate\Support\Facades\Route;

// Auth & Passwords
Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('/logout', 'logout');
    Route::get('/me', 'me');
});

// Route::post('/auth/change-password', [PasswordController::class, 'change']);
Route::post('/teachers/{id}/reset-password-otp', [TeacherController::class, 'resetPassword']);

// Academic Sessions & Terms
Route::prefix('academic-sessions')->controller(AcademicSessionController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
    Route::post('/{id}/set-current', 'setCurrent');

    Route::prefix('/{sessionId}/terms')->controller(TermController::class)->group(function () {
        Route::post('/{id}/set-current', 'setCurrent');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
});

// Class levels & Arms
Route::prefix('class-levels')->controller(ClassLevelController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    Route::delete('/{id}', 'destroy');

    Route::prefix('/{id}/subjects')->controller(ClassLevelController::class)->group(function () {
        Route::get('/', 'availableSubjects');
        Route::post('/sync', 'sync');
        Route::patch('/{subjectId}/toggle-compulsory', 'toggleCompulsory');
    });

    Route::prefix('/{classLevelId}/arms')->controller(ClassArmController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}/assign-teacher', 'assignTeacher');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('/{classLevelId}/arms/{armId}/subjects')->controller(ClassArmSubjectController::class)->group(function () {
        // GET  — list assigned + unassigned subjects for this arm
        Route::get('/', 'index');

        // POST /sync — replace entire subject list atomically
        Route::post('/sync', 'sync');

        // POST /inherit — copy all class-level subjects to this arm
        Route::post('/inherit', 'inheritFromLevel');

        // POST /{subjectId} — add one subject
        Route::post('/{subjectId}', 'attach');

        // DELETE /{subjectId} — remove one subject
        Route::delete('/{subjectId}', 'detach');

        // PATCH /{subjectId}/toggle-compulsory
        Route::patch('/{subjectId}/toggle-compulsory', 'toggleCompulsory');
    });
});

// Subjects
Route::prefix('subjects')->controller(SubjectController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
    // custom
    Route::post('/{id}/assign-teacher', 'assignTeacher');
    Route::delete('/{id}/assignments/{assignmentId}', 'removeTeacher');
});

// Teachers
Route::prefix('teachers')->controller(TeacherController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/import-template', 'downloadImportTemplate');
    Route::post('/import', 'importCsv')->middleware(['permission:manage_staff']);
    Route::get('/{id}/classes', 'classes');
    Route::get('/{id}/subjects', 'subjects');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    Route::post('/{id}/revoke', 'revoke');
    Route::delete('/{id}', 'destroy');
    // custom
    Route::post('/{id}/toggle-active', 'toggleActive');
    Route::post('/{id}/reset-password', 'resetPassword');
    Route::post('/{id}/restore', 'restore');
});

// Students
Route::prefix('students')->controller(StudentController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::patch('/{id}', 'update');
    Route::post('/{id}/revoke', 'revoke');
    Route::post('/{id}/restore', 'restore');
    Route::delete('/{id}', 'destroy');
    // custom-links
    Route::post('/{id}/toggle-active', 'toggleActive');
    Route::post('/{id}/reassign-class', 'reassignClass');
    Route::get('/export', 'exportCsv');
    Route::get('/import-template', 'downloadImportTemplate');
    Route::post('/import', 'importCsv')->middleware(['permission:manage_students']);
    Route::post('/bulk-reset-passwords', 'bulkResetPasswords');
});

// Grading scales (permission-guarded)
Route::prefix('grading-scales')->middleware(['permission:manage_grading_scales'])->controller(GradingScaleController::class)->group(function () {
    Route::get('/', 'index')->name('grading-scales.index');
    Route::post('/', 'store')->name('grading-scales.store');
    Route::get('/{gradingScale}', 'show')->name('grading-scales.show');
    Route::patch('/{gradingScale}', 'update')->name('grading-scales.update');
    Route::delete('/{gradingScale}', 'destroy')->name('grading-scales.destroy');
});

// School settings
Route::prefix('school-settings')->middleware(['role:teacher|school_admin'])->controller(SchoolSettingController::class)->group(function () {
    Route::get('/', 'index');
    Route::patch('/', 'update');

    Route::middleware(['permission:manage_school_settings'])->group(function () {
        Route::get('/assessments', 'assessments');
        Route::patch('/assessments', 'updateAssessments');
        Route::get('/assessment-defaults', 'assessmentDefaults');
        Route::patch('/assessment-defaults', 'updateAssessmentDefaults');
    });
});

Route::middleware(['role:teacher|school_admin'])->group(function () {

    // Exams
    Route::prefix('exams')->controller(ExamController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/publish', 'publish');
        Route::post('/{id}/start-session', 'startSession');
        Route::post('/{id}/end-session', 'endSession');
    });

    // Exam Questions
    Route::prefix('exams/{examId}/questions')->controller(ExamQuestionController::class)->group(function () {
        Route::post('/', 'store');
        Route::post('/auto-generate', 'autoGenerate');
        Route::patch('/{questionId}', 'update');
        Route::delete('/{questionId}', 'destroy');
        Route::post('/reorder', 'reorder');
    });

    // Exam Attendance
    Route::prefix('exams/{id}/attendance')->controller(ExamAttendanceController::class)->group(function () {
        Route::get('/class-students', 'classStudents');
        Route::post('/batch', 'batchStore');
        Route::put('/{studentId}', 'update');
    });

    // Exam Monitoring
    Route::prefix('exams/{id}/monitor')->controller(ExamMonitoringController::class)->group(function () {
        Route::get('/', 'index');
    });

    // Exam Grading
    Route::prefix('exams/{id}/grading')->controller(ExamGradingController::class)->group(function () {
        Route::get('/ungraded-attempts', 'ungradedAttempts');
        Route::get('/attempts/{attemptId}/theory-answers', 'theoryAnswers');
        Route::put('/answers/{answerId}/grade', 'gradeAnswer');
        Route::put('/attempts/{attemptId}/mark-fully-graded', 'markFullyGraded');
        Route::post('/attempts/{attemptId}/recompute-score', 'recomputeScore');
    });

    // Topics
    Route::prefix('topics')->controller(TopicController::class)->group(function () {
        Route::get('/', 'index');   // ?subject_id=&class_level_id=
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Questions
    Route::prefix('questions')->controller(QuestionController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/clone-from-term', 'cloneFromTerm');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/restore', 'restore');
    });

    // Question Options
    Route::prefix('questions/{questionId}/options')->controller(QuestionOptionController::class)->group(function () {
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/reorder', 'reorder');
    });

    // Form helpers
    // Route::prefix('form')->controller(FormDataController::class)->group(function () {
    //     Route::get('/question-bank-data', 'questionBankData')->middleware('httpcache:300');
    // });

});

// ==========================================
// STUDENT ROUTES
// ==========================================
Route::middleware(['role:student'])->group(function () {
    Route::prefix('student/exams')->controller(StudentExamController::class)->group(function () {
        Route::get('/available', 'index');
        Route::get('/{id}', 'show');
        Route::post('/{id}/start', 'start');
        Route::get('/{id}/attempt', 'activeAttempt');
        Route::get('/{id}/questions', 'getQuestions');
        Route::put('/attempts/{id}/answers/{questionId}', 'saveAnswer');
        Route::post('/attempts/{id}/bulk-save', 'bulkSave');
        Route::get('/attempts/{id}/time-remaining', 'timeRemaining');
        Route::post('/attempts/{id}/submit', 'submit');
        Route::post('/attempts/{id}/flag/{questionId}', 'toggleFlag');
        Route::post('/attempts/{id}/suspicious-event', 'logSuspiciousEvent')->middleware('throttle:30,1');
        Route::get('/attempts/{id}/result', 'result');
    });
});
