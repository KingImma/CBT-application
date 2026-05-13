<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ActivityFeedEvent implements ShouldBroadcast
{
    use SerializesModels;
    
    /**
     * @param array<int,mixed> $meta
     */
    public function __construct(
        public readonly string $channelType,
        public readonly string $channelId,
        public readonly string $action,
        public readonly string $description,
        public readonly array  $meta = [],
    ) {}
    
    public function broadcastOn()
    {
        return match ($this->channelType) {
            'super_admin'  => [new PrivateChannel('super-admin.activity')],
            'school_admin' => [new PrivateChannel("school-admin.{$this->channelId}.activity")],
            'teacher'      => [new PrivateChannel("teacher.{$this->channelId}.activity")],
            default        => [],
        };
    }
    
    /**
     * @return string
     */
    public function broadcastAs()
    {
        return 'activity.update';
    }
    
    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        Log::info('Activity Event Feed Broadcasted', [
            'channel_type' => $this->channelType,
            'channel_id'   => $this->channelId,
            'action'      => $this->action,
            'description' => $this->description,
            'timestamp'   => now()->toIso8601String(),
            'meta'        => $this->meta,
        ]);
        return [
            'action'      => $this->action,
            'description' => $this->description,
            'timestamp'   => now()->toIso8601String(),
            'meta'        => $this->meta,
        ];
    }
}
