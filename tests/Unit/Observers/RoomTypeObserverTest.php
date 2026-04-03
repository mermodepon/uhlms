<?php

namespace Tests\Unit\Observers;

use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_created_room_type_notifies_staff(): void
    {
        $this->clearNotifications();

        RoomType::create([
            'name' => 'Suite',
            'base_rate' => 2000,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $notification = $this->findNotificationByTitle('New Room Type Created');
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertStringContainsString('Suite', $data['body']);
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
        $this->clearNotifications();

        $roomType->update(['is_active' => false]);

        $notification = $this->findNotificationByTitle('Room Type Deactivated');
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
        $this->clearNotifications();

        $roomType->update(['room_sharing_type' => 'public']);

        $notification = $this->findNotificationByTitle('Room Type Sharing Updated');
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertStringContainsString('private', $data['body']);
        $this->assertStringContainsString('public', $data['body']);
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
        $this->clearNotifications();

        $roomType->update(['description' => 'Budget-friendly room']);

        $notification = $this->findNotificationByTitle('Room Type Updated');
        $this->assertNotNull($notification);
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
        $this->clearNotifications();

        $roomType->delete();

        $notification = $this->findNotificationByTitle('Room Type Deleted');
        $this->assertNotNull($notification);
    }
}
