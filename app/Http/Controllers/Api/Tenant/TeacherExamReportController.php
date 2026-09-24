<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Reports\BuildExamClassReport;
use App\Domains\Exams\Actions\Results\GenerateBulkResultPdfAction;
use App\Domains\Exams\Actions\Results\GenerateClassCumulativeResultPdfAction;
use App\Domains\Exams\Actions\Results\GenerateCumulativeResultPdfAction;
use App\Domains\Exams\Actions\Results\GenerateExamClassReportPdf;
use App\Domains\Exams\Data\Output\ResultQuestionData;
use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherExamReportController extends Controller
{
    public function __construct(
        private BuildExamClassReport $buildReport,
        private GenerateCumulativeResultPdfAction $generateCumulativePdf,
        private GenerateClassCumulativeResultPdfAction $generateClassCumulativePdf,
        private GenerateBulkResultPdfAction $generateBulkPdf,
        private GenerateExamClassReportPdf $generateExamClassReportPdf
    ) {}

    public function examSummary(ClassArm $classArm, Exam $exam): JsonResponse
    {
        $this->authorize('viewExamReport', [$classArm, $exam]);

        return ApiResponse::success(
            $this->buildReport->execute($classArm, $exam),
            'Exam class report retrieved successfully.'
        );
    }

    public function cumulativeResultPdf(Request $request, string $studentId)
    {
        $validated = $request->validate([
            'academic_session_id' => ['sometimes', 'uuid', 'exists:academic_sessions,id'],
        ]);

        $student = User::where('role', 'student')->findOrFail($studentId);

        $this->authorize('viewStudent', $student);

        $session = isset($validated['academic_session_id'])
            ? AcademicSession::findOrFail($validated['academic_session_id'])
            : AcademicSession::where('is_current', true)->first();

        /*
         * A concluded session has no current term, so the standing session is
         * no longer implied — the caller must identify which session range to
         * report before the cumulative result can be produced.
         */
        if ($session === null) {
            return ApiResponse::error(
                'No academic session is currently active. Pass academic_session_id to identify the session to report.',
                422
            );
        }

        $pdf = $this->generateCumulativePdf->execute($student, $session);

        return $pdf->download($this->generateCumulativePdf->filename($student, $session));
    }

    /** * Generate cumulative result sheets for all students in the class arm. */
    public function classCumulativePdf(ClassArm $classArm, Exam $exam)
    {
        $this->authorize('viewExamReport', [$classArm, $exam]);

        $pdf = $this->generateClassCumulativePdf->execute($classArm, $exam);

        return $pdf->download($this->generateClassCumulativePdf->filename($classArm, $exam));
    }

    /** The class-wide exam summary report (summary grid + roster). */
    public function examSummaryPdf(ClassArm $classArm, Exam $exam)
    {
        $this->authorize('viewExamReport', [$classArm, $exam]); // same gate as examSummary() — no new policy

        $pdf = $this->generateExamClassReportPdf->execute($classArm, $exam);

        return $pdf->download($this->generateExamClassReportPdf->filename($classArm, $exam));
    }

    public function examResultsBulkPdf(ClassArm $classArm, Exam $exam)
    {
        // staff-only — bulk contains every student's answers, same sensitivity as cumulative
        $this->authorize('viewExamReport', [$classArm, $exam]);

        $pdf = $this->generateBulkPdf->execute($classArm, $exam);

        return $pdf->download($this->generateBulkPdf->filename($classArm, $exam));
    }

    public function studentResults(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => ['sometimes', 'uuid', 'exists:exams,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $student = User::where('id', $studentId)->where('role', 'student')->firstOrFail();

        $attempts = ExamAttempt::with([
            'exam.subject',
            'exam.classLevel',
            'exam.examQuestions',
            'answers.question.options',
        ])
            ->where('student_id', $student->id)
            ->whereIn('status', [
                ExamAttemptStatus::Graded->value,
                ExamAttemptStatus::Disqualified->value,
                ExamAttemptStatus::Timed_out->value,
            ])
            ->when(
                isset($validated['exam_id']),
                fn ($query) => $query->where('exam_id', $validated['exam_id'])
            )
            ->latest('submitted_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $results = $attempts->getCollection()->map(function (ExamAttempt $attempt) {
            $examQuestions = $attempt->exam->examQuestions->keyBy('question_id');

            return [
                'attempt_id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'exam_title' => $attempt->exam->title,
                'exam_subject' => $attempt->exam->subject->name,
                'status' => $attempt->status,
                'attempt_number' => $attempt->attempt_number,
                'total_score' => (float) $attempt->total_score,
                'total_marks' => (float) $attempt->exam->total_marks,
                'percentage_score' => (float) $attempt->percentage_score,
                'grade' => $attempt->grade,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'questions' => $attempt->answers
                    ->map(function ($answer) use ($examQuestions) {
                        $examQuestion = $examQuestions->get($answer->question?->id);

                        return $examQuestion
                            ? ResultQuestionData::fromAnswer($answer, $examQuestion, $answer->question)
                            : null;
                    })
                    ->filter()
                    ->values()
                    ->toArray(),
            ];
        });

        return ApiResponse::paginated($attempts, 'Student results retrieved successfully.', $results);
    }
}
