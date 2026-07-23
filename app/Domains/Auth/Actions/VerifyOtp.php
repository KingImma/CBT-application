<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VerifyOtp
{
    private const OTP_MAX_ATTEMPTS = 5;

    private const RESET_TOKEN_TTL_MINUTES = 10;

    public function execute(string $email, string $otp): string
    {
        $email = $this->normalizeEmail($email);

        $record = $this->findOtpRecord($email);

        $this->ensureOtpCanBeVerified($record);

        $this->ensureOtpMatches($record, $email, $otp);

        $this->deleteOtp($email);

        return $this->issueResetToken($email);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function findOtpRecord(string $email): ?object
    {
        return DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();
    }

    private function ensureOtpCanBeVerified(?object $record): void
    {
        if (
            ! $record ||
            now()->gt($record->expires_at) ||
            $record->attempts >= self::OTP_MAX_ATTEMPTS
        ) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired code.',
            ]);
        }
    }

    private function ensureOtpMatches(
        object $record,
        string $email,
        string $otp,
    ): void {
        if (hash_equals($record->token, hash('sha256', $otp))) {
            return;
        }

        $remainingAttempts = $this->incrementAttempts($email);

        if ($remainingAttempts <= 0) {
            $this->deleteOtp($email);

            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect attempts. Request a new code.',
            ]);
        }

        throw ValidationException::withMessages([
            'otp' => "Invalid code. {$remainingAttempts} attempt(s) remaining.",
        ]);
    }

    private function incrementAttempts(string $email): int
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->increment('attempts');

        $attempts = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->value('attempts');

        return self::OTP_MAX_ATTEMPTS - $attempts;
    }

    private function deleteOtp(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }

    private function issueResetToken(string $email): string
    {
        $token = bin2hex(random_bytes(32));

        Cache::put(
            "pwd_reset_token:{$token}",
            $email,
            now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
        );

        return $token;
    }
}
