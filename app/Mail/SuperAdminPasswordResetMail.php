<?php

// - What: password reset mailable specifically for super admins
// - Does: passes name and reset URL — URL points to central admin panel, not a school subdomain
// - Why separate: super admin reset URL structure differs from tenant user reset URLs
// - Expected: super admin receives link to /admin/reset-password on the central domain
// - Alternative: reuse PasswordResetMail with role='super_admin' — less explicit

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SuperAdminPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your EduCBT Admin password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.super-admin-password-reset');
    }

    public function attachments(): array
    {
        return [];
    }
}
