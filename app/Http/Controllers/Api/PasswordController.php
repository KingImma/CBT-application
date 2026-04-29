<?php

// • What: PasswordController — HTTP layer for all password management routes
// • Does: Validates incoming requests, delegates to PasswordService, returns responses
// • Why: Thin controller pattern — all business logic stays in PasswordService.
//        Controllers are just traffic cops: validate in, route to service, respond out.
// • Delivers: 4 clean endpoints for the full password feature
// • Alternative: Move validation to Form Requests (dedicated Request classes) for larger
//               teams or if validators are reused across controllers

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordService $passwordService
    ) {}

    // ─────────────────────────────────────
    // POST /api/password/forgot
    // ─────────────────────────────────────

    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Resolve school name from tenant config for the email template
        // Assumes tenant() helper or config('app.school_name') is available in tenant context
        $schoolName = config('tenant.school_name', 'EduCBT');

        $this->passwordService->sendOtp($request->email, $schoolName);

        // Always return 200 — never reveal whether the email exists
        return response()->json([
            'message' => 'If that email is registered, you will receive a reset code.',
        ]);
    }

    // ─────────────────────────────────────
    // POST /api/password/verify-otp
    // ─────────────────────────────────────

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $resetToken = $this->passwordService->verifyOtp(
            $request->email,
            $request->otp
        );

        return response()->json([
            'reset_token' => $resetToken,
        ]);
    }

    // ─────────────────────────────────────
    // POST /api/password/reset
    // ─────────────────────────────────────

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'reset_token'           => ['required', 'string'],
            'password'              => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        $this->passwordService->resetPassword(
            $request->reset_token,
            $request->password
        );

        return response()->json([
            'message' => 'Password reset successfully. Please log in.',
        ]);
    }

    // ─────────────────────────────────────
    // POST /api/password/change (auth required)
    // ─────────────────────────────────────

    public function change(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        // Check multiple guards: super_admin first, then tenant
        $user = Auth::guard('super_admin')->user() ?? Auth::guard('tenant')->user();

        if (!$user) {
            throw ValidationException::withMessages([
                'auth' => 'Unauthenticated.',
            ]);
        }

        $this->passwordService->changePassword(
            $user,
            $request->current_password,
            $request->password
        );

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}