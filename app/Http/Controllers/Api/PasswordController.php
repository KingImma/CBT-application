<?php

// • What: PasswordController — HTTP layer for OTP-based password reset flow
// • Does: Validates incoming requests, delegates to PasswordService, returns responses
// • Why: Thin controller pattern — all business logic stays in PasswordService.
//        Controllers are just traffic cops: validate in, route to service, respond out.
// • Delivers: 3 clean endpoints for the forgot/verify/reset flow
// • Alternative: Move validation to Form Requests for larger teams

namespace App\Http\Controllers\Api;

use App\Actions\Auth\ChangePassword;
use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\SendOtp;
use App\Actions\Auth\VerifyOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Password;

/**
 * @group Authentication & Onboarding
 * * APIs for user login, password resets, and initial school onboarding.
 */
class PasswordController extends Controller
{
    public function __construct(
        private readonly SendOtp $sendOtp,
        private readonly VerifyOtp $verifyOtp,
        private readonly ResetPassword $resetPassword,
        private readonly ChangePassword $changePassword,
    ) {}

    // ────────────────────────────
    // POST /api/password/forgot
    // ────────────────────────────

    /**
     * Send a password reset OTP to the user's email.
     *
     * @subgroup Password Management
     *
     * @bodyParam email string required User's email address. No-example
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Resolve school name from tenant config for the email template
        $schoolName = config('tenant.school_name', 'EduCBT');

        $this->sendOtp->execute($validated['email'], $schoolName);

        // Always return 200 — never reveal whether the email exists
        return ApiResponse::message('If that email is registered, you will receive a reset code.');
    }

    // ────────────────────────────
    // POST /api/password/verify-otp
    // ────────────────────────────

    /**
     * Verify the OTP and receive a reset token.
     *
     * @subgroup Password Management
     *
     * @bodyParam email string required User's email address. No-example
     * @bodyParam otp string required The 6-digit OTP code. Example: "123456"
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $resetToken = $this->verifyOtp->execute(
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

    /**
     * Reset the password using a verified reset token.
     *
     * @subgroup Password Management
     *
     * @bodyParam reset_token string required The reset token from OTP verification. No-example
     * @bodyParam password string required New password (min 8 chars, must contain number). No-example
     * @bodyParam password_confirmation string required Confirm the new password. No-example
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->resetPassword->execute(
            $validated['reset_token'],
            $validated['password']
        );

        return ApiResponse::message('Password reset successfully. Please log in.');
    }

    /**
     * Change password for an authenticated user.
     *
     * @subgroup Password Management
     *
     * @bodyParam current_password string required Current password. No-example
     * @bodyParam password string required New password (min 8 chars, must contain number). No-example
     * @bodyParam password_confirmation string required Confirm the new password. No-example
     */
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->changePassword->execute(
            $request->user(),
            $validated['current_password'],
            $validated['password']
        );

        return ApiResponse::message('Password changed successfully.');
    }
}
