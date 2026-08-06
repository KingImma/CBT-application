<?php

declare(strict_types=1);

namespace App\Domains\Exams\Listeners;

use App\Domains\Exams\Events\ExamCommentAdded;
use App\Notifications\InAppNotification;

class SendExamCommentNotification
{
    public function handle(ExamCommentAdded $event): void
    {
        $teacher = $event->exam->creator;

        if ($teacher === null) {
            return;
        }

        $teacher->notify(new InAppNotification(
            title: 'New Comment on Your Exam',
            message: "{$event->admin->first_name} {$event->admin->last_name} commented on \"{$event->exam->title}\".",
            type: 'info',
            action: [
                'url' => "/teacher/exams/{$event->exam->id}",
                'label' => 'View Comment',
            ],
        ));
    }
}
