<?php

declare(strict_types=1);

namespace App\Domains\Exams\Listeners;

use App\Events\ExamCompleted;
use App\Models\Tenant\User;
use App\Notifications\InAppNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendExamCompletedNotification implements ShouldQueue
{
    public function handle(ExamCompleted $event)
    {
        $exam = $event->exam;

        $studentIds = $exam->attempts()->pluck('student_id')->unique();
        $recipients = User::whereIn('id', $studentIds)
            ->orWhere('id', $exam->created_by)
            ->get();

        Notification::send($recipients, new InAppNotification(
            title: 'Exam Completed',
            message: 'Your exam has been completed.',
            type: 'info',
            action: [
                'url' => route('exams.report', ['examId' => $exam->id]),
                'label' => 'View Report',
            ],
        ));
    }
}