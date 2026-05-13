<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Events\ActivityFeedEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    public function test_super_admin_channel(): void
    {
        $event = new ActivityFeedEvent(
            channelType: 'super_admin',
            channelId: '',
            action: 'tenant.created',
            description: 'New school registered.',
            meta: ['tenant_id' => 'test-id'],
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-super-admin.activity', $channels[0]->name);
    }

    public function test_school_admin_channel(): void
    {
        $event = new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: 'school-123',
            action: 'student.created',
            description: 'Student John Doe added.',
            meta: ['student_id' => 'uuid-here'],
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-school-admin.school-123.activity', $channels[0]->name);
    }

    public function test_teacher_channel(): void
    {
        $event = new ActivityFeedEvent(
            channelType: 'teacher',
            channelId: 'teacher-456',
            action: 'exam.published',
            description: 'Mid-term exam published.',
            meta: ['exam_id' => 'exam-uuid'],
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-teacher.teacher-456.activity', $channels[0]->name);
    }

    public function test_unknown_channel_type_returns_empty(): void
    {
        $event = new ActivityFeedEvent(
            channelType: 'unknown',
            channelId: 'anything',
            action: 'test',
            description: 'test',
        );

        $this->assertSame([], $event->broadcastOn());
    }

    public function test_broadcast_payload(): void
    {
        $event = new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: 'school-123',
            action: 'student.created',
            description: 'Test student.',
            meta: ['key' => 'value'],
        );

        $payload = $event->broadcastWith();

        $this->assertSame('student.created', $payload['action']);
        $this->assertSame('Test student.', $payload['description']);
        $this->assertSame(['key' => 'value'], $payload['meta']);
        $this->assertArrayHasKey('timestamp', $payload);
    }

    public function test_broadcast_event_name(): void
    {
        $event = new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: 'school-123',
            action: 'test',
            description: 'test',
        );

        $this->assertSame('activity.update', $event->broadcastAs());
    }
}
