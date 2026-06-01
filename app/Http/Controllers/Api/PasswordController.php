<?php

// • What: PasswordController — HTTP layer for OTP-based password reset flow
// • Does: Validates incoming requests, delegates to PasswordService, returns responses
// • Why: Thin controller pattern — all business logic stays in PasswordService.
//        Controllers are just traffic cops: validate in, route to service, respond out.
// • Delivers: 3 clean endpoints for the forgot/verify/reset flow
// • Alternative: Move validation to Form Requests for larger teams

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\OtpService;
use App\Services\Auth\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * @group Authentication & Onboarding
 * * APIs for user login, password resets, and initial school onboarding.
 */
class PasswordController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    // ────────────────────────────
    // POST /api/password/forgot
    // ────────────────────────────

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['bail', 'required', 'email:rfc', 'max:254'],
        ]);

        // Resolve school name from tenant config for the email template
        $schoolName = config('tenant.school_name', 'EduCBT');

        $this->otpService->sendOtp($validated['email'], $schoolName);

        // Always return 200 — never reveal whether the email exists
        return ApiResponse::message('If that email is registered, you will receive a reset code.');
    }

    // ────────────────────────────
    // POST /api/password/verify-otp
    // ────────────────────────────

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['bail', 'required', 'email:rfc', 'max:254'],
            'otp' => ['bail', 'required', 'string', 'size:6'],
        ]);

        $resetToken = $this->otpService->verifyOtp(
            $validated['email'],
            $validated['otp']
        );

        return ApiResponse::success([
            'reset_token' => $resetToken,
        ], 'OTP verified successfully.');
    }

    // ────────────────────────────
    // POST /api/password/reset
    // ────────────────────────────

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        $this->passwordResetService->resetPassword(
            $validated['reset_token'],
            $validated['password']
        );

        return ApiResponse::message('Password reset successfully. Please log in.');
    }

    public function change(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $this->passwordResetService->changePassword(
            $user,
            $validated['current_password'],
            $validated['password']
        );

        return ApiResponse::message('Password changed successfully.');
    }
}
