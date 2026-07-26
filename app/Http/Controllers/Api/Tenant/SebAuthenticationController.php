<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SebAuthenticationController extends Controller
{
    public function authenticate(Request $request): RedirectResponse
    {
        $attemptId = $request->query('attempt_id');
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);

        $frontendHost = tenant('domain') ?? parse_url(config('app.url'), PHP_URL_HOST);
        $frontendBaseUrl = "https://{$frontendHost}";

        // Guard: Attempt must be in progress
        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            return redirect()->away("{$frontendBaseUrl}/seb-expired");
        }

        $student = User::findOrFail($attempt->student_id);

        // Security: Revoke previous SEB tokens to prevent session overlap
        $student->tokens()->where('name', 'seb-active-session')->delete();

        $durationMinutes = $attempt->exam->duration ?? 120;
        $expiration = now()->addMinutes((int) $durationMinutes + 15);

        $token = $student->createToken(
            name: 'seb-active-session',
            abilities: ['exam:take'],
            expiresAt: $expiration
        )->plainTextToken;

        $encodedToken = urlencode($token);

        // 302 Redirect the SEB browser straight to the frontend exam interface
        return redirect()->away("{$frontendBaseUrl}/seb-entry?attempt_id={$attempt->id}&token={$encodedToken}");
    }
}
