<?php

namespace Tests\Unit\Models;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Models\Reservation;
use App\Models\User;
use App\Services\RoomHoldService;
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
        $floor = $overrides['_floor'] ?? $this->createFloor();
        unset($overrides['_room_type']);
        unset($overrides['_floor']);

        return Room::create(array_merge([
            'room_number' => '101',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
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

    public function test_current_private_hold_marks_room_reserved_and_future_hold_does_not(): void
    {
        $roomType = $this->createRoomType('private');
        $room = $this->createRoom(['_room_type' => $roomType]);
        $reservation = Reservation::create([
            'guest_first_name' => 'Jane', 'guest_last_name' => 'Guest', 'guest_email' => 'jane@example.com',
            'guest_phone' => '09171234567', 'preferred_room_type_id' => $roomType->id,
            'check_in_date' => today(), 'check_out_date' => today()->addDays(2), 'number_of_occupants' => 1, 'status' => 'confirmed',
        ]);
        RoomHold::create(['room_id' => $room->id, 'reservation_id' => $reservation->id, 'hold_from' => today(), 'hold_to' => today()->addDays(2), 'hold_type' => 'advance']);

        $room->recalculateStatus();
        $this->assertSame('reserved', $room->fresh()->status);
        $this->assertStringContainsString('Reserved until', $room->fresh()->reservationAvailability()['label']);

        $room->roomHolds()->delete();
        RoomHold::create(['room_id' => $room->id, 'reservation_id' => $reservation->id, 'hold_from' => today()->addDay(), 'hold_to' => today()->addDays(3), 'hold_type' => 'advance']);
        $room->fresh()->recalculateStatus();

        $this->assertSame('available', $room->fresh()->status);
        $this->assertStringContainsString('Upcoming hold:', $room->fresh()->reservationAvailability()['label']);
    }

    public function test_dorm_allocation_includes_current_holds_and_checked_in_guests(): void
    {
        $roomType = $this->createRoomType('public');
        $room = $this->createRoom(['_room_type' => $roomType, 'capacity' => 20]);
        $reservation = Reservation::create([
            'guest_first_name' => 'Dorm', 'guest_last_name' => 'Guest', 'guest_email' => 'dorm@example.com',
            'guest_phone' => '09171234568', 'preferred_room_type_id' => $roomType->id,
            'check_in_date' => today(), 'check_out_date' => today()->addDays(2), 'number_of_occupants' => 5, 'status' => 'confirmed',
        ]);
        RoomHold::create(['room_id' => $room->id, 'reservation_id' => $reservation->id, 'hold_from' => today(), 'hold_to' => today()->addDays(2), 'hold_type' => 'advance', 'held_guest_count' => 5]);
        $staff = User::factory()->create();
        foreach (range(1, 3) as $_) {
            RoomAssignment::create(['reservation_id' => $reservation->id, 'room_id' => $room->id, 'assigned_by' => $staff->id, 'status' => 'checked_in']);
        }

        $allocation = $room->fresh()->reservationAvailability();

        $this->assertSame(5, $allocation['held_beds']);
        $this->assertSame(12, $allocation['available_beds']);
        $this->assertSame('available', $room->fresh()->status);
    }

    public function test_dorm_options_can_require_minimum_beds_and_validate_selected_capacity(): void
    {
        $roomType = $this->createRoomType('public');
        $floor = $this->createFloor();
        $first = $this->createRoom(['_room_type' => $roomType, '_floor' => $floor, 'room_number' => 'Dorm A', 'capacity' => 4]);
        $second = $this->createRoom(['_room_type' => $roomType, '_floor' => $floor, 'room_number' => 'Dorm B', 'capacity' => 4]);
        $reservation = Reservation::create([
            'guest_first_name' => 'Capacity', 'guest_last_name' => 'Guest', 'guest_email' => 'capacity@example.com',
            'guest_phone' => '09171234569', 'preferred_room_type_id' => $roomType->id,
            'check_in_date' => today(), 'check_out_date' => today()->addDays(2), 'number_of_occupants' => 3, 'status' => 'confirmed',
        ]);
        RoomHold::create(['room_id' => $first->id, 'reservation_id' => $reservation->id, 'hold_from' => today(), 'hold_to' => today()->addDays(2), 'hold_type' => 'advance', 'held_guest_count' => 3]);
        RoomHold::create(['room_id' => $second->id, 'reservation_id' => $reservation->id, 'hold_from' => today(), 'hold_to' => today()->addDays(2), 'hold_type' => 'advance', 'held_guest_count' => 2]);

        $service = app(RoomHoldService::class);
        $atLeastTwoBeds = $service->getAvailableRooms($roomType, today(), today()->addDays(2), null, 2);

        $this->assertFalse($atLeastTwoBeds->contains('id', $first->id));
        $this->assertTrue($atLeastTwoBeds->contains('id', $second->id));
        $this->assertTrue($service->selectedDormRoomsCanAccommodate(collect([$first, $second]), today(), today()->addDays(2), 3));
        $this->assertFalse($service->selectedDormRoomsCanAccommodate(collect([$first, $second]), today(), today()->addDays(2), 4));
    }
}
