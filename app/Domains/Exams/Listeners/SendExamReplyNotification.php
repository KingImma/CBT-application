<?php

declare(strict_types=1);

namespace App\Domains\Exams\Listeners;

use App\Domains\Exams\Events\ExamCommentReplied;
use App\Enums\NotificationLabel;
use App\Notifications\InAppNotification;

class SendExamReplyNotification
{
    public function handle(ExamCommentReplied $event): void
    {
        $admin = $event->parentComment->author;

        if ($admin === null) {
            return;
        }

        $teacher = $event->reply->author;
        $teacherName = $teacher !== null
            ? "{$teacher->first_name} {$teacher->last_name}"
            : 'A teacher';

        $admin->notify(new InAppNotification(
            title: 'Teacher Replied to Your Comment',
            message: "{$teacherName} replied to your comment on \"{$event->exam->title}\".",
            type: 'info',
            label: NotificationLabel::Exam->value,
            action: [
                'url' => "/admin/exams/{$event->exam->id}",
                'label' => 'View Reply',
            ],
        ));
    }
}
