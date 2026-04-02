<?php

namespace Tests\Unit\Observers;

use App\Models\Floor;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }

        User::create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    private function createReservation(array $overrides = []): Reservation
    {
        $roomType = RoomType::first() ?? RoomType::create([
            'name' => 'Standard',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        return Reservation::create(array_merge([
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_created_logs_reservation_created(): void
    {
        $reservation = $this->createReservation();

        $log = ReservationLog::where('reservation_id', $reservation->id)
            ->where('event', 'reservation_created')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString($reservation->reference_number, $log->description);
    }

    public function test_created_notifies_staff(): void
    {
        $reservation = $this->createReservation();

        $notification = Notification::where('title', 'New Reservation')
            ->where('message', 'like', '%' . $reservation->reference_number . '%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('info', $notification->type);
    }

    public function test_updated_status_to_approved_logs_event(): void
    {
        $reservation = $this->createReservation(['status' => 'pending']);
        ReservationLog::query()->delete();
        Notification::query()->delete();

        $reservation->update(['status' => 'approved']);

        $log = ReservationLog::where('reservation_id', $reservation->id)
            ->where('event', 'reservation_approved')
            ->first();

        $this->assertNotNull($log);
    }

    public function test_updated_status_to_declined_logs_event(): void
    {
        $reservation = $this->createReservation(['status' => 'pending']);
        ReservationLog::query()->delete();

        $reservation->update(['status' => 'declined']);

        $log = ReservationLog::where('event', 'reservation_declined')->first();
        $this->assertNotNull($log);
    }

    public function test_updated_status_to_cancelled_closes_open_assignments(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $reservation = $this->createReservation(['status' => 'checked_in']);
        $roomType = RoomType::first();
        $floor = Floor::create(['name' => 'F1', 'level' => 1, 'is_active' => true]);
        $room = Room::create([
            'room_number' => '101',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $assignment = RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'assigned_by' => auth()->id(),
        ]);

        ReservationLog::query()->delete();
        Notification::query()->delete();

        $reservation->update(['status' => 'cancelled']);

        $assignment->refresh();
        $this->assertNotNull($assignment->checked_out_at);
    }

    public function test_updated_status_change_notifies_staff(): void
    {
        $reservation = $this->createReservation(['status' => 'pending']);
        Notification::query()->delete();

        $reservation->update(['status' => 'approved']);

        $notification = Notification::where('title', 'Reservation Status Updated')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('success', $notification->type); // approved = success
    }

    public function test_deleted_notifies_staff(): void
    {
        $reservation = $this->createReservation();
        Notification::query()->delete();

        $reservation->delete();

        $notification = Notification::where('title', 'Reservation Deleted')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('warning', $notification->type);
    }
}
