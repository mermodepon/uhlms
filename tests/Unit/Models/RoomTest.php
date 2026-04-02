<?php

namespace Tests\Unit\Models;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    private function createRoomType(string $sharing = 'private'): RoomType
    {
        return RoomType::create([
            'name' => 'Test Type',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => $sharing,
            'is_active' => true,
        ]);
    }

    private function createFloor(): Floor
    {
        return Floor::create(['name' => 'Ground', 'level' => 1, 'is_active' => true]);
    }

    private function createRoom(array $overrides = []): Room
    {
        $roomType = $overrides['_room_type'] ?? $this->createRoomType();
        unset($overrides['_room_type']);

        return Room::create(array_merge([
            'room_number' => '101',
            'room_type_id' => $roomType->id,
            'floor_id' => $this->createFloor()->id,
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ], $overrides));
    }

    public function test_fillable_attributes(): void
    {
        $room = new Room;
        $fillable = $room->getFillable();

        $this->assertContains('room_number', $fillable);
        $this->assertContains('room_type_id', $fillable);
        $this->assertContains('floor_id', $fillable);
        $this->assertContains('capacity', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('is_active', $fillable);
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $room = $this->createRoom(['is_active' => 1]);
        $this->assertIsBool($room->is_active);
        $this->assertTrue($room->is_active);
    }

    public function test_room_type_relationship(): void
    {
        $room = new Room;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $room->roomType()
        );
    }

    public function test_floor_relationship(): void
    {
        $room = new Room;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $room->floor()
        );
    }

    public function test_room_assignments_relationship(): void
    {
        $room = new Room;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $room->roomAssignments()
        );
    }

    public function test_current_occupancy_with_no_assignments(): void
    {
        $room = $this->createRoom();
        $this->assertEquals(0, $room->currentOccupancy());
    }

    public function test_is_full_private_room_empty(): void
    {
        $roomType = $this->createRoomType('private');
        $room = $this->createRoom(['_room_type' => $roomType]);

        $this->assertFalse($room->isFull());
    }

    public function test_available_slots_empty_room(): void
    {
        $room = $this->createRoom(['capacity' => 4]);
        $this->assertEquals(4, $room->availableSlots());
    }

    public function test_is_available_active_available_not_full(): void
    {
        $room = $this->createRoom(['status' => 'available', 'is_active' => true]);
        $this->assertTrue($room->isAvailable());
    }

    public function test_is_not_available_when_inactive(): void
    {
        $room = $this->createRoom(['status' => 'available', 'is_active' => false]);
        $this->assertFalse($room->isAvailable());
    }

    public function test_is_not_available_when_maintenance(): void
    {
        $room = $this->createRoom(['status' => 'maintenance', 'is_active' => true]);
        $this->assertFalse($room->isAvailable());
    }

    public function test_recalculate_status_skips_maintenance(): void
    {
        $room = $this->createRoom(['status' => 'maintenance']);
        $room->recalculateStatus();

        $this->assertEquals('maintenance', $room->fresh()->status);
    }

    public function test_recalculate_status_skips_inactive(): void
    {
        $room = $this->createRoom(['status' => 'inactive']);
        $room->recalculateStatus();

        $this->assertEquals('inactive', $room->fresh()->status);
    }

    public function test_recalculate_status_available_when_no_assignments(): void
    {
        $room = $this->createRoom(['status' => 'occupied']);
        $room->recalculateStatus();

        $this->assertEquals('available', $room->fresh()->status);
    }
}
