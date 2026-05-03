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
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordService
{
    private const OTP_EXPIRY_MINUTES = 15;

    private const OTP_MAX_ATTEMPTS = 5;

    private const RATE_LIMIT_MAX = 3;

    private const RATE_LIMIT_WINDOW_MIN = 60;

    private const RESET_TOKEN_TTL_MIN = 10;

    // ──────────────────────────────────
    // FORGOT PASSWORD — Step 1: Issue OTP
    // ──────────────────────────────────

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

        // Determine which User model to use based on tenancy context
        $userModel = $this->resolveUserModel();
        $user = $userModel::where('email', $email)->first();

        if ($user) {
            // Clean up any existing tokens for this email
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            // Generate 6-digit OTP, hash it before storing
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => hash('sha256', $otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'created_at' => now(),
            ]);

            $toEmail = config('mail.override_address', $email);
            Mail::to($toEmail)->send(new PasswordResetOtpMail($otp, $schoolName));
        }

        // Increment rate limit counter — even for non-existent emails
        // This prevents attackers from detecting valid emails via rate-limit discrepancies
        Cache::put(
            $rateLimitKey,
            $attempts + 1,
            now()->addMinutes(self::RATE_LIMIT_WINDOW_MIN)
        );
    }

    // ──────────────────────────────────
    // FORGOT PASSWORD — Step 2: Verify OTP
    // ──────────────────────────────────

    public function verifyOtp(string $email, string $otp): string
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        // Treat not-found, expired, and exhausted the same way — no information leakage
        if (! $record || now()->gt($record->expires_at) || $record->attempts >= self::OTP_MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired code.',
            ]);
        }

        // Compare hashes — never compare plain OTP to plain OTP
        if (! hash_equals($record->token, hash('sha256', $otp))) {
            DB::table('password_reset_tokens')->where('email', $email)->increment('attempts');

            $updated = DB::table('password_reset_tokens')->where('email', $email)->first();
            $remaining = self::OTP_MAX_ATTEMPTS - $updated->attempts;

            if ($remaining <= 0) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                throw ValidationException::withMessages([
                    'otp' => 'Too many incorrect attempts. Request a new code.',
                ]);
            }

            throw ValidationException::withMessages([
                'otp' => "Invalid code. {$remaining} attempt(s) remaining.",
            ]);
        }

        // OTP valid — delete it (single-use), issue a short-lived reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return $this->issueResetToken($email);
    }

    // ──────────────────────────────────
    // FORGOT PASSWORD — Step 3: Reset Password
    // ──────────────────────────────────

    public function resetPassword(string $resetToken, string $newPassword): void
    {
        $email = $this->decodeResetToken($resetToken);

        $userModel = $this->resolveUserModel();
        $user = $userModel::where('email', $email)->firstOrFail();

        $user->update(['password' => Hash::make($newPassword)]);

        // Invalidate all sessions — force re-login after a reset
        // This is different from change-password where we preserve the session
        $user->tokens()->delete();
    }

    // ──────────────────────────────────
    // RESET PASSWORD (For specific user)
    // ──────────────────────────────────

    public function resetPasswordForUser(Authenticatable $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);

        // Invalidate all sessions — force re-login after a reset
        $user->tokens()->delete();
    }

    // ──────────────────────────────────
    // CHANGE PASSWORD (Authenticated)
    // ──────────────────────────────────

    public function changePassword(Authenticatable $user, string $currentPassword, string $newPassword): void
    {
        // Verify the current password before allowing change
        // Think of this as the lock before the new key — you must prove
        // ownership before overwriting credentials.
        if (! Hash::check($currentPassword, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        // Use the password attribute directly for update
        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        // DO NOT delete tokens here — change password preserves the active session.
        // Forgetting is different from choosing to change.
    }

    // ──────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────

    /**
     * Resolve the appropriate User model based on tenancy context.
     * Returns Tenant\User if in tenant context, otherwise central User model.
     */
    private function resolveUserModel(): string
    {
        return tenant() ? \App\Models\Tenant\User::class : User::class;
    }

    private function issueResetToken(string $email): string
    {
        // Using a signed payload stored in Redis for stateful revocability.
        // Alternative: JWT with 'sub' = email and short expiry. Simpler but not revocable.
        $token = bin2hex(random_bytes(32));
        $key = "pwd_reset_token:{$token}";

        Cache::put($key, $email, now()->addMinutes(self::RESET_TOKEN_TTL_MIN));

        return $token;
    }

    private function decodeResetToken(string $token): string
    {
        $key = "pwd_reset_token:{$token}";
        $email = Cache::get($key);

        if (! $email) {
            throw ValidationException::withMessages([
                'reset_token' => 'Your reset session has expired. Please start again.',
            ]);
        }

        // Consume the token — single-use
        Cache::forget($key);

        return $email;
    }
}
