<?php 

namespace App\Mail;

use App\Mail\Data\ExamResultMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExamResultMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ExamResultMailData $data)
    {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->data->schoolName} Exam Result Is Ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.exam-result',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
