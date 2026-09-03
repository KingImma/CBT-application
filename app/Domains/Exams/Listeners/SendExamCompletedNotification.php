<?php

declare(strict_types=1);

namespace App\Domains\Exams\Listeners;

use App\Domains\Exams\Events\ExamCompleted;
use App\Models\Tenant\User;
use App\Notifications\InAppNotification;
use App\Enums\NotificationLabel;
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

        $reportUrl = $exam->class_arm_id !== null
            ? route('exams.report', ['classArm' => $exam->class_arm_id, 'exam' => $exam->id])
            : null;

        Notification::send($recipients, new InAppNotification(
            title: 'Exam Completed',
            message: 'Your exam has been completed.',
            type: 'info',
            label: NotificationLabel::Exam->value,
            action: [
                'url' => $reportUrl,
                'label' => 'View Report',
            ],
        ));
    }
}
