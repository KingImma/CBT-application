<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PasswordResetMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function forgot_password_sends_the_otp_to_the_requesting_email_when_no_override_is_configured(): void
    {
        Mail::fake();
        config(['mail.override_address' => null]);

        User::factory()->create(['email' => 'student@example.com']);

        app(OtpService::class)->sendOtp('student@example.com', 'EduCBT');

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail): bool {
            return $mail->hasTo('student@example.com');
        });
    }

    #[Test]
    public function empty_mail_override_address_falls_back_to_the_requesting_email(): void
    {
        Mail::fake();
        config(['mail.override_address' => '   ']);

        User::factory()->create(['email' => 'student@example.com']);

        app(OtpService::class)->sendOtp('student@example.com', 'EduCBT');

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail): bool {
            return $mail->hasTo('student@example.com');
        });
    }

    #[Test]
    public function valid_mail_override_address_receives_the_otp_instead(): void
    {
        Mail::fake();
        config(['mail.override_address' => 'developer@example.com']);

        User::factory()->create(['email' => 'student@example.com']);

        app(OtpService::class)->sendOtp('student@example.com', 'EduCBT');

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail): bool {
            return $mail->hasTo('developer@example.com')
                && ! $mail->hasTo('student@example.com');
        });
    }

    #[Test]
    public function invalid_requesting_email_is_rejected_before_mail_is_built(): void
    {
        Mail::fake();

        $this->expectException(ValidationException::class);

        app(OtpService::class)->sendOtp('', 'EduCBT');

        Mail::assertNothingSent();
    }

    #[Test]
    public function invalid_mail_override_address_fails_with_a_clear_configuration_error(): void
    {
        Mail::fake();
        config(['mail.override_address' => 'not-an-email']);

        User::factory()->create(['email' => 'student@example.com']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAIL_OVERRIDE_ADDRESS must be a valid email address when set.');

        app(OtpService::class)->sendOtp('student@example.com', 'EduCBT');

        Mail::assertNothingSent();
    }
}
