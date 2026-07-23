<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SendOtp
{
    private const OTP_EXPIRY_MINUTES = 15;

    private const RATE_LIMIT_MAX = 3;

    private const RATE_LIMIT_WINDOW_MIN = 60;

    public function execute(string $email, string $schoolName): void
    {
        $email = $this->normalizeEmail($email);

        $this->ensureRateLimitNotExceeded($email);

        $user = $this->findUser($email);

        if ($user) {
            $otp = $this->storeOtp($email);
            $this->queueOtpEmail($email, $otp, $schoolName);
        }

        $this->recordResetAttempt($email);
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

    private function ensureRateLimitNotExceeded(string $email): void
    {
        $attempts = Cache::get($this->rateLimitKey($email), 0);

        if ($attempts >= self::RATE_LIMIT_MAX) {
            throw ValidationException::withMessages([
                'email' => 'Too many reset requests. Please wait before trying again.',
            ]);
        }
    }

    private function findUser(string $email): ?User
    {
        $userModel = $this->resolveUserModel();

        return $userModel::where('email', $email)->first();
    }

    private function resolveUserModel(): string
    {
        return tenant() ? \App\Models\Tenant\User::class : User::class;
    }

    private function storeOtp(string $email): string
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        $otp = str_pad(
            (string) random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT,
        );

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => hash('sha256', $otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'created_at' => now(),
        ]);

        return $otp;
    }

    private function queueOtpEmail(
        string $email,
        string $otp,
        string $schoolName,
    ): void {
        Mail::to($this->resolveMailRecipient($email))
            ->queue(new PasswordResetOtpMail($otp, $schoolName));
    }

    private function recordResetAttempt(string $email): void
    {
        $key = $this->rateLimitKey($email);

        Cache::put(
            $key,
            Cache::get($key, 0) + 1,
            now()->addMinutes(self::RATE_LIMIT_WINDOW_MINUTES),
        );
    }

    private function rateLimitKey(string $email): string
    {
        return "pwd_otp_rl:{$email}";
    }

    private function resolveMailRecipient(string $email): string
    {
        $overrideAddress = config('mail.override_address');

        if (is_string($overrideAddress)) {
            $overrideAddress = trim($overrideAddress);
        }

        $recipient = $overrideAddress ?: $email;

        if (
            ! is_string($recipient) ||
            ! filter_var($recipient, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                'MAIL_OVERRIDE_ADDRESS must be a valid email address when set.',
            );
        }

        return $recipient;
    }
}
