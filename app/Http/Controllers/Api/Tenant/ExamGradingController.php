<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamGradingAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamGradingController extends Controller
{
    public function __construct(
        private ExamGradingAction $gradingAction,
    ) {}

    /**
     * Recompute the score for an exam attempt.
     *
     * @subgroup Exam Grading
     *
     * @urlParam attemptId string required The attempt UUID.
     */
    public function recomputeScore(string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('grade', $attempt);

        $attempt = $this->gradingAction->recomputeScore($attempt);

        return ApiResponse::success($attempt, 'Score recomputed.');
    }
}
