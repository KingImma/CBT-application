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
        // Utilizes your existing action to build the aggregated class report
        $report = $buildReportAction->execute($exam);

        return ApiResponse::success(
            'Exam class report retrieved successfully.',
            $report
        );
    }

    /**
     * Retrieve all graded/completed exam results for a specific student.
     */
    public function studentResults(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'exam_id'  => ['sometimes', 'uuid', 'exists:exams,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        // Ensure the ID belongs to a valid student in this tenant
        $student = User::where('id', $studentId)
            ->where('role', 'student') 
            ->firstOrFail();

        $perPage = (int) ($validated['per_page'] ?? 20);

        // Fetch the student's attempts with the necessary relational data
        $attempts = ExamAttempt::with([
            'exam.subject',
            'exam.classLevel',
            'exam.examQuestions',
            'answers.question.options',
        ])
            ->where('student_id', $student->id)
            ->whereIn('status', [
                ExamAttemptStatus::Completed->value,
                ExamAttemptStatus::Graded->value,
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