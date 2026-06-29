<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\Report\BuildExamClassReport;
use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherExamReportController extends Controller
{
    /**
     * Generate and retrieve a comprehensive class report for a specific exam.
     */
    public function examSummary(Exam $exam, BuildExamClassReport $buildReportAction): JsonResponse
    {
        $report = $buildReportAction->execute($exam);

        return ApiResponse::success(
            'Exam class report retrieved successfully.',
            $report
        );
    }

    /**
     * Retrieve all finalized exam results for a specific student.
     */
    public function studentResults(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'exam_id'  => ['sometimes', 'uuid', 'exists:exams,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $student = User::where('id', $studentId)
            ->where('role', 'student') 
            ->firstOrFail();

        $perPage = (int) ($validated['per_page'] ?? 20);

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
                ExamAttemptStatus::Failed->value,
            ])
            ->when(
                isset($validated['exam_id']),
                fn ($query) => $query->where('exam_id', $validated['exam_id'])
            )
            ->latest('submitted_at')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $attempts,
            'Student results retrieved successfully.'
        );
    }
}