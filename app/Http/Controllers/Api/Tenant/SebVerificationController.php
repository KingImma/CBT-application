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

        $currentToken = $student->currentAccessToken();

        if (
            $currentToken === null ||
            $currentToken->name !== 'seb-launch-token' ||
            ! $currentToken->can('exam:take')
        ) {
            return ApiResponse::error('Exam session is expired or invalid.', 403);
         }

        $currentToken->delete();

        $durationMinutes = $attempt->exam->duration_minutes;
        $expiration = now()->addMinutes((int) $durationMinutes + 15);

        $examSessionToken = $student->createToken(
            name: 'seb-active-session',
            abilities: ['exam:take'],
            expiresAt: $expiration
        )->plainTextToken;

        return ApiResponse::success([
            'attempt' => $attempt,
            'session_token' => $examSessionToken
        ], 'SEB launch verified.');
    }
}
