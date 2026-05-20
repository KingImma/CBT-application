<?php

// - What: mailable for the school welcome email sent after registration
// - Does: passes school name, admin name, handle and login URL to Blade template
// - Why separate class per email: explicit, debuggable, each mail has its own log entry in Mailtrap
// - Expected: school admin receives login link immediately after POST /api/schools/setup
// - Alternative: generic BaseMail with a 'type' param — harder to trace in Mailtrap logs

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $schoolName,
        public readonly string $adminName,
        public readonly string $adminEmail,
        public readonly string $handle,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to EduCBT — {$this->schoolName} is ready!",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.schools.welcome');
    }

    public function attachments(): array
    {
        return [];
    }
}
