<?php

declare(strict_types=1);

namespace App\Domains\Exams\Mail;

use App\Models\Tenant\Exam;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExamActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Exam $exam,
        public readonly string $studentName,
        public readonly string $schoolName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Exam Available: '.$this->exam->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.exam-activated',
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
