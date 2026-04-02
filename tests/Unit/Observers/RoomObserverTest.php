<?php

namespace Tests\Unit\Observers;

use App\Models\Floor;
use App\Models\Notification;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomObserverTest extends TestCase
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

    private function createRoom(string $status = 'available'): Room
    {
        $roomType = RoomType::first() ?? RoomType::create([
            'name' => 'Standard',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $floor = Floor::create(['name' => 'F' . uniqid(), 'level' => 1, 'is_active' => true]);

        return Room::create([
            'room_number' => 'R' . uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => $status,
            'is_active' => true,
        ]);
    }

    public function test_created_room_notifies_staff(): void
    {
        Notification::query()->delete();

        $room = $this->createRoom();

        $notification = Notification::where('title', 'New Room Created')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('success', $notification->type);
    }

    public function test_updated_status_notifies_staff(): void
    {
        $room = $this->createRoom('available');
        Notification::query()->delete();

        $room->update(['status' => 'maintenance']);

        $notification = Notification::where('title', 'Room Status Changed')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('warning', $notification->type); // maintenance = warning
    }

    public function test_updated_is_active_notifies_staff(): void
    {
        $room = $this->createRoom();
        Notification::query()->delete();

        $room->update(['is_active' => false]);

        $notification = Notification::where('title', 'Room Deactivated')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('warning', $notification->type);
    }

    public function test_deleted_room_notifies_staff(): void
    {
        $room = $this->createRoom();
        Notification::query()->delete();

        $room->delete();

        $notification = Notification::where('title', 'Room Deleted')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('danger', $notification->type);
    }
}
