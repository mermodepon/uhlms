<?php

namespace Tests\Unit\Observers;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomAssignmentObserverTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        if (!DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }

        $user = User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
        $this->actingAs($user);

        $roomType = RoomType::create([
            'name' => 'Dorm',
            'base_rate' => 500,
            'pricing_type' => 'per_person',
            'room_sharing_type' => 'public',
            'is_active' => true,
        ]);

        $floor = Floor::create(['name' => 'F1', 'level' => 1, 'is_active' => true]);
        $this->room = Room::create([
            'room_number' => '101',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->reservation = Reservation::create([
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => 'john@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'approved',
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

    public function test_created_assignment_logs_guest_checked_in(): void
    {
        ReservationLog::query()->delete();

        RoomAssignment::create([
            'reservation_id' => $this->reservation->id,
            'room_id' => $this->room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'assigned_by' => auth()->id(),
        ]);

        $log = ReservationLog::where('event', 'guest_checked_in')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Jane Doe', $log->description);
    }

    public function test_created_assignment_notifies_staff(): void
    {
        $this->clearNotifications();

        RoomAssignment::create([
            'reservation_id' => $this->reservation->id,
            'room_id' => $this->room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'assigned_by' => auth()->id(),
        ]);

        $notification = $this->findNotificationByTitle('Room Assigned');
        $this->assertNotNull($notification);
    }

    public function test_checked_out_assignment_logs_event(): void
    {
        $assignment = RoomAssignment::create([
            'reservation_id' => $this->reservation->id,
            'room_id' => $this->room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'assigned_by' => auth()->id(),
        ]);

        ReservationLog::query()->delete();

        $assignment->update([
            'status' => 'checked_out',
            'checked_out_at' => now(),
        ]);

        $log = ReservationLog::where('event', 'guest_checked_out')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Jane Doe', $log->description);
    }

    public function test_deleted_assignment_logs_event(): void
    {
        $assignment = RoomAssignment::create([
            'reservation_id' => $this->reservation->id,
            'room_id' => $this->room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'assigned_by' => auth()->id(),
        ]);

        ReservationLog::query()->delete();
        $this->clearNotifications();

        $assignment->delete();

        $log = ReservationLog::where('event', 'room_assignment_removed')->first();
        $this->assertNotNull($log);
    }

    public function test_deleted_assignment_notifies_staff(): void
    {
        $assignment = RoomAssignment::create([
            'reservation_id' => $this->reservation->id,
            'room_id' => $this->room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'assigned_by' => auth()->id(),
        ]);

        $this->clearNotifications();

        $assignment->delete();

        $notification = $this->findNotificationByTitle('Room Assignment Removed');
        $this->assertNotNull($notification);
    }
}
