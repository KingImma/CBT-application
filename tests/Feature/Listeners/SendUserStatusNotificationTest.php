<?php

declare(strict_types=1);

use App\Models\Tenant\User;
use App\Domains\Tenancy\Events\UserActivated;
use App\Domains\Tenancy\Events\UserDeactivated;
use App\Notifications\InAppNotification;
use Illuminate\Support\Facades\Notification;

test('it sends a success notification on activation', function () {
    Notification::fake();

    $user = User::factory()->create(['is_active' => true]);
    
    UserActivated::dispatch($user);

    Notification::assertSentTo(
        $user, 
        InAppNotification::class, 
        function (InAppNotification $notification) {
            return $notification->type === 'success' 
                && $notification->title === 'Account Activated';
        }
    );
});

test('it sends a warning notification on deactivation', function () {
    Notification::fake();

    $user = User::factory()->create(['is_active' => false]);
    
    UserDeactivated::dispatch($user);

    Notification::assertSentTo(
        $user, 
        InAppNotification::class, 
        function (InAppNotification $notification) {
            return $notification->type === 'warning' 
                && $notification->title === 'Account Deactivated';
        }
    );
});