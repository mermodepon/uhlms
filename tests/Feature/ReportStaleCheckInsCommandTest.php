<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportStaleCheckInsCommandTest extends TestCase
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

    public function test_it_reports_checked_in_reservations_past_checkout(): void
    {
        $roomType = RoomType::create([
            'name' => 'Report Room '.uniqid(),
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $floor = Floor::create(['name' => 'Report Floor '.uniqid(), 'level' => 1, 'is_active' => true]);
        $room = Room::create([
            'room_number' => 'R'.uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 1,
            'status' => 'occupied',
            'is_active' => true,
        ]);
        $staff = User::create([
            'name' => 'Report Staff',
            'email' => 'report-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $reservation = Reservation::create([
            'guest_first_name' => 'Stale',
            'guest_last_name' => 'Guest',
            'guest_email' => 'stale-'.uniqid().'@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => today()->subDays(3),
            'check_out_date' => today()->subDay(),
            'number_of_occupants' => 1,
            'status' => 'checked_in',
        ]);
        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'assigned_by' => $staff->id,
            'checked_in_at' => now()->subDays(3),
            'status' => 'checked_in',
        ]);

        $this->assertDatabaseHas('room_assignments', [
            'reservation_id' => $reservation->id,
            'status' => 'checked_in',
        ]);
        $this->assertSame(1, $reservation->roomAssignments()->where('status', 'checked_in')->count());

        $this->artisan('reservations:report-stale-checkins')
            ->expectsOutputToContain($reservation->reference_number)
            ->expectsOutputToContain('1 stale checked-in reservation(s) reported.')
            ->assertSuccessful();
    }
}
