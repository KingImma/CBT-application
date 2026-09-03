<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Listeners;

use App\Events\UserActivated;
use App\Events\UserDeactivated;
use App\Notifications\InAppNotification;
use App\Enums\NotificationLabel;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendUserStatusNotification implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(UserActivated|UserDeactivated $event): void
    {
        $isActive = $event instanceof UserActivated;
        $user = $event->user;

        $user->notify(new InAppNotification(
            title: $isActive ? 'User Activated' : 'User Deactivated',
            message: $isActive
                ? 'Your account has been activated.'
                : 'Your account has been deactivated. Contact your school administrator for assistance.',
            type: $isActive ? 'success' : 'danger',
            label: NotificationLabel::Exam->value,
        ));
    }
}