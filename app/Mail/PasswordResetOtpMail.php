<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly string $schoolName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ' . $this->schoolName . ' password reset code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-otp',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
