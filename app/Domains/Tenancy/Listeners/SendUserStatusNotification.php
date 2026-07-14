<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Listeners;

use App\Events\UserActivated;
use App\Events\UserDeactivated;
use App\Notifications\InAppNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendUserStatusNotification implements ShouldQueue
{
    public function handle(UserActivated | UserDeactivated $event)
    {
        $isActive = $event instanceof UserActivated;

        $user = $event->user;

        $event->notify(new InAppNotification(
            title: $isActive ? 'User Activated' : 'User Deactivated',
            message: $isActive ? 'Your account has been activated.' : 'Your account has been deactivated. Contact your school administrator for assistance.',
            type: $isActive ? 'success' : 'danger',
        ));
    }
}