<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Services\RoomUtilizationCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomUtilizationCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }
    }

    public function test_calendar_builds_room_rows_holds_assignments_and_unassigned_requests(): void
    {
        $floor = Floor::create([
            'name' => 'Ground Floor',
            'level' => 1,
            'is_active' => true,
        ]);

        $privateType = RoomType::create([
            'name' => 'Executive',
            'base_rate' => 1000,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $dormType = RoomType::create([
            'name' => 'Dormitory',
            'base_rate' => 200,
            'pricing_type' => 'per_person',
            'room_sharing_type' => 'public',
            'is_active' => true,
        ]);

        $privateRoom = Room::create([
            'room_number' => '101',
            'room_type_id' => $privateType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'reserved',
            'is_active' => true,
        ]);

        $dormRoom = Room::create([
            'room_number' => '201',
            'room_type_id' => $dormType->id,
            'floor_id' => $floor->id,
            'capacity' => 10,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $maintenanceRoom = Room::create([
            'room_number' => '301',
            'room_type_id' => $privateType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'maintenance',
            'is_active' => true,
        ]);

        $from = Carbon::parse('2026-07-08');
        $to = Carbon::parse('2026-07-10');

        $heldReservation = $this->reservation($privateType, $from, $to, 'approved', 2);
        RoomHold::create([
            'room_id' => $privateRoom->id,
            'reservation_id' => $heldReservation->id,
            'hold_from' => $from,
            'hold_to' => $to,
            'hold_type' => 'advance',
        ]);

        $checkedInReservation = $this->reservation($dormType, $from, $to, 'checked_in', 2);
        foreach (['Ana', 'Ben'] as $firstName) {
            RoomAssignment::create([
                'reservation_id' => $checkedInReservation->id,
                'room_id' => $dormRoom->id,
                'guest_first_name' => $firstName,
                'guest_last_name' => 'Guest',
                'guest_gender' => 'Female',
                'status' => 'checked_in',
                'checked_in_at' => $from,
                'detailed_checkout_datetime' => $to,
            ]);
        }

        $this->reservation($dormType, $from, $to, 'pending', 4);

        $data = app(RoomUtilizationCalendarService::class)->build([
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]);

        $this->assertCount(3, $data['rows']);
        $this->assertSame(1, $data['summary']['unassigned_reservations']);

        $privateRow = collect($data['rows'])->firstWhere('room_id', $privateRoom->id);
        $this->assertSame('2/2 held', $privateRow['cells']['2026-07-08']['slot_summary']['label']);
        $this->assertSame('hold', $privateRow['cells']['2026-07-08']['events'][0]['type']);

        $dormRow = collect($data['rows'])->firstWhere('room_id', $dormRoom->id);
        $this->assertSame('2/10 occupied', $dormRow['cells']['2026-07-08']['slot_summary']['label']);
        $this->assertCount(1, $dormRow['cells']['2026-07-08']['events']);

        $maintenanceRow = collect($data['rows'])->firstWhere('room_id', $maintenanceRoom->id);
        $this->assertSame('room_state', $maintenanceRow['cells']['2026-07-08']['events'][0]['type']);
    }

    public function test_calendar_filters_by_room_type_and_floor(): void
    {
        $floorA = Floor::create(['name' => 'A', 'level' => 1, 'is_active' => true]);
        $floorB = Floor::create(['name' => 'B', 'level' => 2, 'is_active' => true]);
        $typeA = RoomType::create(['name' => 'A', 'base_rate' => 100, 'pricing_type' => 'flat_rate', 'room_sharing_type' => 'private', 'is_active' => true]);
        $typeB = RoomType::create(['name' => 'B', 'base_rate' => 100, 'pricing_type' => 'flat_rate', 'room_sharing_type' => 'private', 'is_active' => true]);

        Room::create(['room_number' => 'A1', 'room_type_id' => $typeA->id, 'floor_id' => $floorA->id, 'capacity' => 2, 'status' => 'available', 'is_active' => true]);
        Room::create(['room_number' => 'B1', 'room_type_id' => $typeB->id, 'floor_id' => $floorB->id, 'capacity' => 2, 'status' => 'available', 'is_active' => true]);

        $data = app(RoomUtilizationCalendarService::class)->build([
            'date_from' => '2026-07-08',
            'date_to' => '2026-07-08',
            'floor_id' => $floorA->id,
            'room_type_id' => $typeA->id,
        ]);

        $this->assertCount(1, $data['rows']);
        $this->assertSame('A1', $data['rows'][0]['room_number']);
    }

    private function reservation(RoomType $roomType, Carbon $from, Carbon $to, string $status, int $occupants): Reservation
    {
        return Reservation::create([
            'guest_first_name' => 'Test',
            'guest_last_name' => uniqid('Guest'),
            'guest_email' => uniqid('guest').'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $from,
            'check_out_date' => $to,
            'number_of_occupants' => $occupants,
            'status' => $status,
        ]);
    }
}
