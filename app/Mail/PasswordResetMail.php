<?php
// - What: mailable for password reset emails to school admins and teachers
// - Does: passes name, reset URL, and role label to Blade template
// - Why role param: the template renders role-specific context ("your teacher account")
// - Expected: user receives reset link valid for 60 minutes, pointing to their school subdomain
// - Alternative: single generic reset mail — loses the role-specific messaging

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $resetUrl,
        public readonly string $role,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your EduCBT password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }

    public function attachments(): array
    {
        return [];
    }
}