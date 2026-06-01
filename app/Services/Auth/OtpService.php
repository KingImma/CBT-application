<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OtpService
{
    private const OTP_EXPIRY_MINUTES = 15;

    private const OTP_MAX_ATTEMPTS = 5;

    private const RATE_LIMIT_MAX = 3;

    private const RATE_LIMIT_WINDOW_MIN = 60;

    private const RESET_TOKEN_TTL_MIN = 10;

    public function sendOtp(string $email, string $schoolName): void
    {
        $email = $this->normalizeEmail($email);

        $rateLimitKey = "pwd_otp_rl:{$email}";
        $attempts = Cache::get($rateLimitKey, 0);

        if ($attempts >= self::RATE_LIMIT_MAX) {
            throw ValidationException::withMessages([
                'email' => 'Too many reset requests. Please wait before trying again.',
            ]);
        }

        $userModel = $this->resolveUserModel();
        $user = $userModel::where('email', $email)->first();

        if ($user) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => hash('sha256', $otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'created_at' => now(),
            ]);

            $toEmail = $this->resolveMailRecipient($email);
            Mail::to($toEmail)->send(new PasswordResetOtpMail($otp, $schoolName));
        }

        Cache::put(
            $rateLimitKey,
            $attempts + 1,
            now()->addMinutes(self::RATE_LIMIT_WINDOW_MIN)
        );
    }

    public function verifyOtp(string $email, string $otp): string
    {
        $email = $this->normalizeEmail($email);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || now()->gt($record->expires_at) || $record->attempts >= self::OTP_MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired code.',
            ]);
        }

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

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return $this->issueResetToken($email);
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'A valid email address is required.',
            ]);
        }

        return $email;
    }

    private function resolveUserModel(): string
    {
        return tenant() ? \App\Models\Tenant\User::class : User::class;
    }

    private function resolveMailRecipient(string $email): string
    {
        $overrideAddress = config('mail.override_address');

        if (is_string($overrideAddress)) {
            $overrideAddress = trim($overrideAddress);
        }

        $recipient = $overrideAddress ?: $email;

        if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('MAIL_OVERRIDE_ADDRESS must be a valid email address when set.');
        }

        return $recipient;
    }

    private function issueResetToken(string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $key = "pwd_reset_token:{$token}";

        Cache::put($key, $email, now()->addMinutes(self::RESET_TOKEN_TTL_MIN));

        return $token;
    }

    public function decodeResetToken(string $token): string
    {
        $key = "pwd_reset_token:{$token}";
        $email = Cache::get($key);

        if (! $email) {
            throw ValidationException::withMessages([
                'reset_token' => 'Your reset session has expired. Please start again.',
            ]);
        }

        Cache::forget($key);

        return $email;
    }
}
