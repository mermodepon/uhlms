<?php

namespace Tests\Unit\Observers;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    protected function findNotificationByTitle(string $title): ?object
    {
        return DB::table('notifications')
            ->where('data', 'like', '%"title":"'.$title.'"%')
            ->first();
    }

    protected function clearNotifications(): void
    {
        DB::table('notifications')->delete();
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
        $this->clearNotifications();

        $room = $this->createRoom();

        $notification = $this->findNotificationByTitle('New Room Created');
        $this->assertNotNull($notification);
    }

    public function test_updated_status_notifies_staff(): void
    {
        $room = $this->createRoom('available');
        $this->clearNotifications();

        $room->update(['status' => 'maintenance']);

        $notification = $this->findNotificationByTitle('Room Status Changed');
        $this->assertNotNull($notification);
    }

    public function test_updated_is_active_notifies_staff(): void
    {
        $room = $this->createRoom();
        $this->clearNotifications();

        $room->update(['is_active' => false]);

        $notification = $this->findNotificationByTitle('Room Deactivated');
        $this->assertNotNull($notification);
    }

    public function test_deleted_room_notifies_staff(): void
    {
        $room = $this->createRoom();
        $this->clearNotifications();

        $room->delete();

        $notification = $this->findNotificationByTitle('Room Deleted');
        $this->assertNotNull($notification);
    }
}
