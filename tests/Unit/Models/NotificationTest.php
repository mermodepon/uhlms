<?php

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ], $overrides));
    }

    public function test_fillable_attributes(): void
    {
        $notification = new Notification;
        $fillable = $notification->getFillable();

        $this->assertContains('title', $fillable);
        $this->assertContains('message', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('is_read', $fillable);
        $this->assertContains('notifiable_type', $fillable);
        $this->assertContains('notifiable_id', $fillable);
    }

    public function test_casts(): void
    {
        $notification = new Notification;
        $casts = $notification->getCasts();

        $this->assertEquals('boolean', $casts['is_read']);
        $this->assertEquals('datetime', $casts['read_at']);
    }

    public function test_notifiable_relationship(): void
    {
        $notification = new Notification;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphTo::class,
            $notification->notifiable()
        );
    }

    public function test_creator_relationship(): void
    {
        $notification = new Notification;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $notification->creator()
        );
    }

    public function test_mark_as_read(): void
    {
        $user = $this->createUser();
        $notification = Notification::create([
            'title' => 'Test',
            'message' => 'Test message',
            'type' => 'info',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'is_read' => false,
        ]);

        $notification->markAsRead();
        $notification->refresh();

        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_does_not_update_if_already_read(): void
    {
        $user = $this->createUser();
        $notification = Notification::create([
            'title' => 'Test',
            'message' => 'Test message',
            'type' => 'info',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'is_read' => true,
            'read_at' => now()->subHour(),
        ]);

        $originalReadAt = $notification->read_at->toDateTimeString();
        $notification->markAsRead();
        $notification->refresh();

        $this->assertEquals($originalReadAt, $notification->read_at->toDateTimeString());
    }

    public function test_mark_as_unread(): void
    {
        $user = $this->createUser();
        $notification = Notification::create([
            'title' => 'Test',
            'message' => 'Test message',
            'type' => 'info',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'is_read' => true,
            'read_at' => now(),
        ]);

        $notification->markAsUnread();
        $notification->refresh();

        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->read_at);
    }

    public function test_create_notification_static_method(): void
    {
        $user = $this->createUser();

        $notification = Notification::createNotification(
            $user,
            'Test Title',
            'Test Message',
            'warning',
            'test_category',
            '/test/url',
            $user->id
        );

        $this->assertEquals('Test Title', $notification->title);
        $this->assertEquals('Test Message', $notification->message);
        $this->assertEquals('warning', $notification->type);
        $this->assertEquals('test_category', $notification->category);
        $this->assertEquals('/test/url', $notification->action_url);
        $this->assertEquals(User::class, $notification->notifiable_type);
        $this->assertEquals($user->id, $notification->notifiable_id);
    }

    public function test_dispatches_notification_created_event_on_create(): void
    {
        $notification = new Notification;
        $events = (new \ReflectionClass($notification))->getProperty('dispatchesEvents')->getValue($notification);

        $this->assertArrayHasKey('created', $events);
        $this->assertEquals(\App\Events\NotificationCreated::class, $events['created']);
    }
}
