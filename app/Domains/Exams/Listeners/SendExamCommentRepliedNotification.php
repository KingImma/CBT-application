<?php

declare(strict_types=1);

namespace App\Domains\Exams\Listeners;

use App\Events\ExamCommentReplied;
use App\Notifications\InAppNotification;

class SendExamCommentRepliedNotification
{
    public function handle(ExamCommentReplied $event): void
    {
        $admin = $event->parentComment->author; // was ->admin — relation renamed

        if ($admin === null) {
            return;
        }

        $admin->notify(new InAppNotification(
            title: 'Teacher Replied to Your Review Comment',
            message: "Reply on \"{$event->exam->title}\": {$event->reply->comment}",
            type: 'info',
            action: [
                'url' => "/admin/exams/{$event->exam->id}/review",
                'label' => 'View Reply',
            ],
        ));
    }
}
