<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamGradingAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ExamGradingController extends Controller
{
    public function __construct(
        private ExamGradingAction $gradingAction,
    ) {}

    public function recomputeScore(string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('grade', $attempt);

        $attempt = $this->gradingAction->recomputeScore($attempt);

        return ApiResponse::success($attempt, 'Score recomputed.');
    }
}
