<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeSchoolMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $adminName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to EduCBT - Your School is Ready!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.schools.welcome',
            with: [
                'loginUrl' => "https://{$this->tenant->handle}.educbt.com/login",
            ]
        );
    }
}