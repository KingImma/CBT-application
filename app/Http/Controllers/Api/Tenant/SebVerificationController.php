<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ExamAttempt;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SebVerificationController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $attemptId = $request->query('attempt_id');
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);
        $student = $request->user('tenant');

        if ($attempt->student_id !== $student->id || $attempt->status !== ExamAttemptStatus::InProgress->value) {
            return ApiResponse::error('Exam session is expired or invalid.', 403);
        }

        if ($request->user()->currentAccessToken()->name === 'seb-launch-token') {
            $request->user()->currentAccessToken()->delete();
        }

        $examSessionToken = $student->createToken('seb-active-session')->plainTextToken;

        return ApiResponse::success([
            'attempt' => $attempt,
            'session_token' => $examSessionToken
        ], 'SEB launch verified.');
    }
}
