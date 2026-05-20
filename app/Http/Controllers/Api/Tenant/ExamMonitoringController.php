<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamMonitoringController extends Controller
{
    public function index(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('viewMonitoring', $exam);

        $perPage = (int) $request->get('per_page', 50);

        $attempts = ExamAttempt::with('student:id,first_name,last_name')
            ->forExam($examId)
            ->inProgress()
            ->paginate($perPage);

        $data = $attempts->getCollection()->map(function ($attempt) {
            $exam = $attempt->exam;
            $timeRemaining = $attempt->getTimeRemainingSeconds();
            $answeredCount = $attempt->answers()->whereNotNull('answered_at')->count();
            $totalQuestions = $exam->examQuestions()->count();
            $suspiciousCount = count($attempt->suspicious_events ?? []);

            return [
                'attempt_id' => $attempt->id,
                'student_name' => $attempt->student->first_name.' '.$attempt->student->last_name,
                'progress' => $answeredCount,
                'total_questions' => $totalQuestions,
                'time_remaining_seconds' => $timeRemaining,
                'status' => $attempt->status,
                'suspicious_event_count' => $suspiciousCount,
            ];
        });

        return ApiResponse::paginated(
            $attempts->setCollection($data),
            'Active attempts retrieved.'
        );
    }
}
