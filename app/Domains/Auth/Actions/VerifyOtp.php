<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyOtp
{
    private const OTP_EXPIRY_MINUTES = 15;

    private const OTP_MAX_ATTEMPTS = 5;

    private const RESET_TOKEN_TTL_MIN = 10;

    public function execute(string $email, string $otp): string
    {
        $email = strtolower(trim($email));

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        $isTokenExpiredOrInvalid = ! $record || now()->gt($record->expires_at) || $record->attempts >= self::OTP_MAX_ATTEMPTS;

        if ($isTokenExpiredOrInvalid) {
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

    private function issueResetToken(string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $key = "pwd_reset_token:{$token}";

        Cache::put($key, $email, now()->addMinutes(self::RESET_TOKEN_TTL_MIN));

        return $token;
    }
}
