<?php

namespace Tests\Unit\Observers;

use App\Models\Notification;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTypeObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    public function test_created_room_type_notifies_staff(): void
    {
        Notification::query()->delete();

        RoomType::create([
            'name' => 'Suite',
            'base_rate' => 2000,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $notification = Notification::where('title', 'New Room Type Created')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Suite', $notification->message);
        $this->assertStringContainsString('Private', $notification->message);
    }

    public function test_updated_is_active_notifies_staff(): void
    {
        $roomType = RoomType::create([
            'name' => 'Dorm',
            'base_rate' => 300,
            'pricing_type' => 'per_person',
            'room_sharing_type' => 'public',
            'is_active' => true,
        ]);
        Notification::query()->delete();

        $roomType->update(['is_active' => false]);

        $notification = Notification::where('title', 'Room Type Deactivated')->first();
        $this->assertNotNull($notification);
    }

    public function test_updated_sharing_type_notifies_staff(): void
    {
        $roomType = RoomType::create([
            'name' => 'Flex',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        Notification::query()->delete();

        $roomType->update(['room_sharing_type' => 'public']);

        $notification = Notification::where('title', 'Room Type Sharing Updated')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('private', $notification->message);
        $this->assertStringContainsString('public', $notification->message);
    }

    public function test_updated_other_fields_notifies_staff(): void
    {
        $roomType = RoomType::create([
            'name' => 'Budget',
            'base_rate' => 100,
            'pricing_type' => 'per_person',
            'room_sharing_type' => 'public',
            'is_active' => true,
        ]);
        Notification::query()->delete();

        $roomType->update(['description' => 'Budget-friendly room']);

        $notification = Notification::where('title', 'Room Type Updated')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('info', $notification->type);
    }

    public function test_deleted_room_type_notifies_staff(): void
    {
        $roomType = RoomType::create([
            'name' => 'Legacy',
            'base_rate' => 100,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        Notification::query()->delete();

        $roomType->delete();

        $notification = Notification::where('title', 'Room Type Deleted')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('danger', $notification->type);
    }
}
