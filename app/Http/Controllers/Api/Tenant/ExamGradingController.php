<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Attempts\GradeExamAttempt;
use App\Domains\Exams\Data\Output\ExamResultData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ExamGradingController extends Controller
{
    public function __construct(private GradeExamAttempt $gradeAttempt) {}

    /**
     * Recompute a single attempt's score.
     * Useful after a manual answer correction or a grading-rule change.
     */
    public function recomputeScore(Exam $exam, ExamAttempt $attempt): JsonResponse
    {
        $this->authorize('grade', $exam);

        $graded = $this->gradeAttempt->execute($attempt);

        return ApiResponse::success($graded, 'Score recomputed.');
    }

    /**
     * Full per-question breakdown for one attempt — the teacher's
     * "show your working" view, distinct from the student's own result view.
     */
    public function viewAttemptResult(Exam $exam, ExamAttempt $attempt): JsonResponse
    {
        $this->authorize('view', $exam);

        return ApiResponse::success(
            ExamResultData::fromAttempt($attempt),
            'Attempt result retrieved.'
        );
    }
}
