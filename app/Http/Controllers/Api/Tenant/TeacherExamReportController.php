<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\Report\BuildExamClassReport;
use App\Data\Exam\Output\ResultQuestionData;
use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherExamReportController extends Controller
{
    public function __construct(private BuildExamClassReport $buildReport) {}

    public function examSummary(ClassArm $classArm, Exam $exam): JsonResponse
    {
        $this->authorize('viewExamReport', [$classArm, $exam]);

        return ApiResponse::success(
            $this->buildReport->execute($classArm, $exam),
            'Exam class report retrieved successfully.'
        );
    }

    public function studentResults(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'exam_id'  => ['sometimes', 'uuid', 'exists:exams,id'],
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
                'attempt_id'         => $attempt->id,
                'exam_id'            => $attempt->exam_id,
                'exam_title'         => $attempt->exam->title,
                'status'             => $attempt->status,
                'attempt_number'     => $attempt->attempt_number,
                'total_score'        => (float) $attempt->total_score,
                'total_marks'        => (float) $attempt->exam->total_marks,
                'percentage_score'   => (float) $attempt->percentage_score,
                'grade'              => $attempt->grade,
                'submitted_at'       => $attempt->submitted_at?->toIso8601String(),
                'questions'          => $attempt->answers
                    ->map(function ($answer) use ($examQuestions) {
                        $examQuestion = $examQuestions->get($answer->question->id);

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
