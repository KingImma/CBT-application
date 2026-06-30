<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamGradingAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use App\Transformers\Tenant\ExamResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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

    public function viewAttemptResult(Exam $exam, ExamAttempt $attempt)
    {
        // 1. Authorize: Ensure the teacher has access to view this exam
        $this->authorize('view', $exam);

        // 2. Return the data structure mapped through the Resource
        // The fromAttempt method handles the logic of loading relations
        // and mapping answers to your ResultQuestionData objects.
        return response()->json([
            'data' => ExamResultData::fromAttempt($attempt),
        ]);
    }
}
