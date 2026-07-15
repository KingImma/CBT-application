<?php

declare(strict_types=1);

use App\Models\Tenant\User;
use App\Domains\Tenancy\Events\UserActivated;
use App\Domains\Tenancy\Events\UserDeactivated;
use Illuminate\Support\Facades\Event;

test('it dispatches UserDeactivated and deletes tokens when deactivated', function () {
    Event::fake([UserDeactivated::class, UserActivated::class]);

    // Create an active user with a Sanctum token
    $user = User::factory()->create(['is_active' => true]);
    $user->createToken('test-token');

    expect($user->tokens()->count())->toBe(1);

    // Deactivate
    $user->update(['is_active' => false]);

    Event::assertDispatched(UserDeactivated::class, fn ($event) => $event->user->id === $user->id);
    Event::assertNotDispatched(UserActivated::class);
    
    // Assert tokens were instantly revoked
    expect($user->tokens()->count())->toBe(0);
});

test('it dispatches UserActivated when activated', function () {
    Event::fake([UserDeactivated::class, UserActivated::class]);

    $user = User::factory()->create(['is_active' => false]);

    // Activate
    $user->update(['is_active' => true]);

    Event::assertDispatched(UserActivated::class, fn ($event) => $event->user->id === $user->id);
    Event::assertNotDispatched(UserDeactivated::class);
});
