<?php

namespace Tests\Unit\Events;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCreatedTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(): Notification
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        return Notification::create([
            'title' => 'Test Title',
            'message' => 'Test Message',
            'type' => 'info',
            'category' => 'test',
            'action_url' => '/test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'is_read' => false,
        ]);
    }

    public function test_event_broadcasts_on_correct_channel(): void
    {
        $notification = $this->createNotification();
        $event = new NotificationCreated($notification);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertEquals(
            'notifications.user.' . $notification->notifiable_id,
            $channels[0]->name
        );
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $notification = $this->createNotification();
        $event = new NotificationCreated($notification);

        $this->assertEquals('notification.created', $event->broadcastAs());
    }

    public function test_broadcast_with_contains_expected_data(): void
    {
        $notification = $this->createNotification();
        $event = new NotificationCreated($notification);

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('category', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertArrayHasKey('created_at', $data);

        $this->assertEquals('Test Title', $data['title']);
        $this->assertEquals('Test Message', $data['message']);
        $this->assertEquals('info', $data['type']);
        $this->assertEquals('test', $data['category']);
        $this->assertEquals('/test', $data['action_url']);
    }
}
