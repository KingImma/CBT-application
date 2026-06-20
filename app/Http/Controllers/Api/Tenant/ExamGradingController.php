<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamGradingAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Exam Administration
 * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamGradingController extends Controller
{
    /**
     * Recompute the score for an exam attempt.
     *
     * @subgroup Exam Grading
     *
     * @urlParam attemptId string required The attempt UUID.
     */
    public function recomputeScore(Exam $exam, ExamAttempt $attempt, ExamGradingAction $action): JsonResponse
    {
        $this->authorize('grade', $exam); 

        $gradedAttempt = $action->execute($attempt);

        return ApiResponse::success($gradedAttempt, 'Score recomputed.');
    }
}
