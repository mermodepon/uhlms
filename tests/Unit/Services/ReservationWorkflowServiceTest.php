<?php

namespace Tests\Unit\Services;

use App\Mail\SendPaymentLinkMail;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ReservationWorkflowService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReservationWorkflowService::class);

        if (! DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Workflow Tester',
            'email' => 'workflow-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function createRoomType(string $sharing = 'private'): RoomType
    {
        return RoomType::create([
            'name' => 'Type '.uniqid(),
            'base_rate' => 500,
            'pricing_type' => $sharing === 'private' ? 'flat_rate' : 'per_person',
            'room_sharing_type' => $sharing,
            'is_active' => true,
        ]);
    }

    private function createRoom(RoomType $roomType, string $status = 'available'): Room
    {
        $floor = Floor::create([
            'name' => 'Floor '.uniqid(),
            'level' => 1,
            'is_active' => true,
        ]);

        return Room::create([
            'room_number' => 'R'.uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function createReservation(RoomType $roomType, string $status = 'pending'): Reservation
    {
        return Reservation::create([
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => 'john-'.uniqid().'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'status' => $status,
        ]);
    }

    public function test_approve_can_optionally_create_room_holds(): void
    {
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType('private');
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType);

        $result = $this->service->approve($reservation, [
            'admin_notes' => 'Looks good',
            'assigned_room_ids' => [$room->id],
        ]);

        $fresh = $reservation->fresh();

        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame(1, $result['room_count']);
        $this->assertNull($result['hold_error']);
        $this->assertDatabaseHas('room_holds', [
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'hold_type' => 'advance',
        ]);
    }

    public function test_approve_can_hold_rooms_grouped_by_requested_room_type(): void
    {
        $this->actingAs($this->createUser());

        $executive = $this->createRoomType('private');
        $dormitory = $this->createRoomType('public');
        $executiveRoom = $this->createRoom($executive);
        $dormRoomA = $this->createRoom($dormitory);
        $dormRoomB = $this->createRoom($dormitory);
        $reservation = $this->createReservation($executive);
        $reservation->update(['number_of_occupants' => 7]);
        $reservation->roomRequests()->createMany([
            [
                'room_type_id' => $executive->id,
                'requested_room_count' => 1,
                'occupant_count' => 1,
                'sort_order' => 0,
            ],
            [
                'room_type_id' => $dormitory->id,
                'requested_room_count' => 2,
                'occupant_count' => 6,
                'sort_order' => 1,
            ],
        ]);

        $result = $this->service->approve($reservation, [
            'assigned_room_ids_by_type' => [
                $executive->id => [$executiveRoom->id],
                $dormitory->id => [$dormRoomA->id, $dormRoomB->id],
            ],
        ]);

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame(3, $result['room_count']);
        $this->assertDatabaseHas('room_holds', [
            'reservation_id' => $reservation->id,
            'room_id' => $executiveRoom->id,
            'held_guest_count' => null,
        ]);
        $this->assertDatabaseHas('room_holds', [
            'reservation_id' => $reservation->id,
            'room_id' => $dormRoomA->id,
            'held_guest_count' => 4,
        ]);
        $this->assertDatabaseHas('room_holds', [
            'reservation_id' => $reservation->id,
            'room_id' => $dormRoomB->id,
            'held_guest_count' => 2,
        ]);
    }

    public function test_cancel_releases_existing_holds(): void
    {
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType('private');
        $room = $this->createRoom($roomType, 'reserved');
        $reservation = $this->createReservation($roomType, 'confirmed');

        RoomHold::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);

        $this->service->cancel($reservation, 'Guest requested cancellation');

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertDatabaseMissing('room_holds', [
            'reservation_id' => $reservation->id,
        ]);
    }

    public function test_mark_confirmed_from_online_payment_does_not_bypass_pending_review(): void
    {
        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType, 'pending');

        $this->service->markConfirmedFromOnlinePayment($reservation);

        $fresh = $reservation->fresh();

        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->approved_at);
    }

    public function test_mark_confirmed_from_online_payment_does_not_confirm_without_room_hold(): void
    {
        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType, 'approved');
        $reservation->update(['approved_at' => now()]);

        $this->service->markConfirmedFromOnlinePayment($reservation);

        $this->assertSame('approved', $reservation->fresh()->status);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'event' => 'payment_received_without_room_hold',
        ]);
    }

    public function test_mark_confirmed_from_online_payment_updates_room_held_approved_reservation(): void
    {
        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType, 'approved');
        $reservation->update(['approved_at' => now()]);

        RoomHold::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);

        $this->service->markConfirmedFromOnlinePayment($reservation);

        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_approve_without_room_hold_does_not_issue_guest_payment_link(): void
    {
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType);

        $staleToken = (string) Str::uuid();
        $reservation->update([
            'payment_link_token' => $staleToken,
            'payment_link_expires_at' => now()->subDay(),
        ]);

        $approvalTime = Carbon::parse('2026-05-02 10:00:00');
        $this->travelTo($approvalTime);

        $this->service->approve($reservation, ['admin_notes' => 'Approved for payment']);

        $fresh = $reservation->fresh();

        $this->assertSame('approved', $fresh->status);
        $this->assertNull($fresh->payment_link_token);
        $this->assertNull($fresh->payment_link_expires_at);
    }

    public function test_approve_with_room_hold_refreshes_guest_payment_link_window_from_approval_time(): void
    {
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType);

        $staleToken = (string) Str::uuid();
        $reservation->update([
            'payment_link_token' => $staleToken,
            'payment_link_expires_at' => now()->subDay(),
        ]);

        $approvalTime = Carbon::parse('2026-05-02 10:00:00');
        $this->travelTo($approvalTime);

        $this->service->approve($reservation, [
            'admin_notes' => 'Approved for payment',
            'assigned_room_ids' => [$room->id],
        ]);

        $fresh = $reservation->fresh();

        $this->assertSame('confirmed', $fresh->status);
        $this->assertNotSame($staleToken, $fresh->payment_link_token);
        $this->assertTrue($fresh->payment_link_expires_at?->equalTo($approvalTime->copy()->addHours(48)));
    }

    public function test_approve_with_room_hold_caps_payment_link_expiry_at_checkout_end(): void
    {
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType);
        $reservation->update([
            'check_in_date' => '2026-05-01',
            'check_out_date' => '2026-05-02',
        ]);

        $approvalTime = Carbon::parse('2026-05-02 10:00:00');
        $this->travelTo($approvalTime);

        $this->service->approve($reservation, [
            'admin_notes' => 'Approved for payment',
            'assigned_room_ids' => [$room->id],
        ]);

        $fresh = $reservation->fresh();

        $this->assertSame('confirmed', $fresh->status);
        $this->assertTrue($fresh->payment_link_expires_at?->equalTo(Carbon::parse('2026-05-02 23:59:59')));
    }

    public function test_refresh_guest_payment_link_rotates_token_and_can_email_guest(): void
    {
        Mail::fake();
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType, 'approved');
        RoomHold::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);
        $oldToken = $reservation->payment_link_token;

        $result = $this->service->refreshGuestPaymentLink($reservation, true);
        $fresh = $result['reservation'];

        $this->assertTrue($result['emailed']);
        $this->assertSame('approved', $fresh->status);
        $this->assertNotSame($oldToken, $fresh->payment_link_token);
        $this->assertTrue($fresh->isPaymentLinkValid());

        Mail::assertSent(SendPaymentLinkMail::class, function ($mail) use ($fresh) {
            return $mail->reservation->is($fresh);
        });

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'event' => 'payment_link_refreshed',
        ]);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'event' => 'payment_link_resent',
        ]);
    }

    public function test_refresh_guest_payment_link_caps_expiry_at_checkout_end(): void
    {
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType, 'approved');
        $reservation->update([
            'approved_at' => now(),
            'check_in_date' => '2026-05-01',
            'check_out_date' => '2026-05-02',
        ]);
        RoomHold::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);

        $this->travelTo(Carbon::parse('2026-05-02 10:00:00'));

        $result = $this->service->refreshGuestPaymentLink($reservation);

        $this->assertTrue($result['reservation']->payment_link_expires_at?->equalTo(Carbon::parse('2026-05-02 23:59:59')));
    }

    public function test_refresh_guest_payment_link_rejects_reservations_without_room_holds(): void
    {
        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType, 'approved');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only reservations with active room holds can receive refreshed payment links.');

        $this->service->refreshGuestPaymentLink($reservation);
    }

    public function test_checkout_closes_open_assignments_and_updates_reservation(): void
    {
        $this->actingAs($this->createUser());

        $roomType = $this->createRoomType('private');
        $room = $this->createRoom($roomType, 'occupied');
        $reservation = $this->createReservation($roomType, 'checked_in');

        $assignment = RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'assigned_by' => auth()->id(),
        ]);

        $this->service->checkOut($reservation, now(), 'Late departure');

        $assignment->refresh();

        $this->assertSame('checked_out', $reservation->fresh()->status);
        $this->assertSame('checked_out', $assignment->status);
        $this->assertNotNull($assignment->checked_out_at);
        $this->assertStringContainsString('Late departure', (string) $assignment->remarks);
    }
}
