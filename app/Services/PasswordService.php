<?php

// • What: PasswordService — core business logic for all password operations
// • Does: Orchestrates OTP generation/verification, reset token lifecycle,
//         and password changes. Keeps all password logic out of controllers.
// • Why: Fat-service/thin-controller pattern. The multi-step OTP flow has
//        real complexity (rate limiting, hashing, expiry, attempts) that
//        doesn't belong in a controller. Services are testable in isolation.
// • Delivers: Clean controller delegation — every controller method becomes
//             a 3-line call into this service
// • Alternative: Use Laravel's built-in Password Broker — but it's designed
//               for magic-link flows and doesn't support OTP + attempt tracking
//               out of the box. Rolling custom is the right call here.

namespace App\Services;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;  

class PasswordService
{
    private const OTP_EXPIRY_MINUTES    = 15;
    private const OTP_MAX_ATTEMPTS      = 5;
    private const RATE_LIMIT_MAX        = 3;
    private const RATE_LIMIT_WINDOW_MIN = 60;
    private const RESET_TOKEN_TTL_MIN   = 10;

    // ──────────────────────────────────────────
    // FORGOT PASSWORD — Step 1: Issue OTP
    // ──────────────────────────────────────────

    public function sendOtp(string $email, string $schoolName): void
    {
        // Rate limit: 3 requests per email per hour (Redis)
        // Think of this as a turnstile: it lets a few people through before locking up.
        $rateLimitKey = "pwd_otp_rl:{$email}";
        $attempts = Cache::get($rateLimitKey, 0);

        if ($attempts >= self::RATE_LIMIT_MAX) {
            throw ValidationException::withMessages([
                'email' => 'Too many reset requests. Please wait before trying again.',
            ]);
        }

        // Look up user — but respond generically regardless (no enumeration)
        $user = User::where('email', $email)->first();

        if ($user) {
            // Clean up any existing tokens for this email
            PasswordResetToken::where('email', $email)->delete();

            // Generate 6-digit OTP, hash it before storing
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetToken::create([
                'email'      => $email,
                'token'      => hash('sha256', $otp),
                'attempts'   => 0,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            ]);

            Mail::to($email)->queue(new PasswordResetOtpMail($otp, $schoolName));
        }

        // Increment rate limit counter — even for non-existent emails
        // This prevents attackers from detecting valid emails via rate-limit discrepancies
        Cache::put(
            $rateLimitKey,
            $attempts + 1,
            now()->addMinutes(self::RATE_LIMIT_WINDOW_MIN)
        );
    }

    // ──────────────────────────────────────────
    // FORGOT PASSWORD — Step 2: Verify OTP
    // ──────────────────────────────────────────

    public function verifyOtp(string $email, string $otp): string
    {
        $record = PasswordResetToken::where('email', $email)->first();

        // Treat not-found, expired, and exhausted the same way — no information leakage
        if (!$record || $record->isExpired() || $record->isExhausted()) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired code.',
            ]);
        }

        // Compare hashes — never compare plain OTP to plain OTP
        if (!hash_equals($record->token, hash('sha256', $otp))) {
            $record->increment('attempts');

            $remaining = self::OTP_MAX_ATTEMPTS - $record->fresh()->attempts;

            if ($remaining <= 0) {
                $record->delete();
                throw ValidationException::withMessages([
                    'otp' => 'Too many incorrect attempts. Request a new code.',
                ]);
            }

            throw ValidationException::withMessages([
                'otp' => "Invalid code. {$remaining} attempt(s) remaining.",
            ]);
        }

        // OTP valid — delete it (single-use), issue a short-lived reset token
        $record->delete();

        return $this->issueResetToken($email);
    }

    // ──────────────────────────────────────────
    // FORGOT PASSWORD — Step 3: Reset Password
    // ──────────────────────────────────────────

    public function resetPassword(string $resetToken, string $newPassword): void
    {
        $email = $this->decodeResetToken($resetToken);

        $user = User::where('email', $email)->firstOrFail();

        $user->update(['password' => Hash::make($newPassword)]);

        // Invalidate all sessions — force re-login after a reset
        // This is different from change-password where we preserve the session
        $user->tokens()->delete();
    }

    // ──────────────────────────────────────────
    // CHANGE PASSWORD (Authenticated)
    // ──────────────────────────────────────────

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        // Verify the current password before allowing change
        // Think of this as the lock before the new key — you must prove
        // ownership before overwriting credentials.
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($newPassword)]);

        // DO NOT delete tokens here — change password preserves the active session.
        // Forgetting is different from choosing to change.
    }

    // ──────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────

    private function issueResetToken(string $email): string
    {
        // Using a signed payload stored in Redis for stateful revocability.
        // Alternative: JWT with 'sub' = email and short expiry. Simpler but not revocable.
        $token = bin2hex(random_bytes(32));
        $key   = "pwd_reset_token:{$token}";

        Cache::put($key, $email, now()->addMinutes(self::RESET_TOKEN_TTL_MIN));

        return $token;
    }

    private function decodeResetToken(string $token): string
    {
        $key   = "pwd_reset_token:{$token}";
        $email = Cache::get($key);

        if (!$email) {
            throw ValidationException::withMessages([
                'reset_token' => 'Your reset session has expired. Please start again.',
            ]);
        }

        // Consume the token — single-use
        Cache::forget($key);

        return $email;
    }
}