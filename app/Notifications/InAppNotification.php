<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class InAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $type = 'info', // info, success, warning, error
        public readonly ?array $action = null  // e.g., ['url' => '/exams/1', 'label' => 'View']
    ) {}

    /**
     * Store in DB for history, push via Reverb for real-time.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Data stored in the `data` column of the notifications table.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'action' => $this->action,
        ];
    }

    /**
     * Data pushed through the WebSocket.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id, // The notification UUID
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'action' => $this->action,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * This defines the JS event name Laravel Echo listens for.
     */
    public function broadcastType(): string
    {
        return 'InAppNotification';
    }
}
