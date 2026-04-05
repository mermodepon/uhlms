<?php

namespace Tests\Unit\Services;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckInServiceExtendedTest extends TestCase
{
    use RefreshDatabase;

    private CheckInService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CheckInService;

        if (! DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }
    }

    private function createUser(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'user' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function createRoomType(string $sharing = 'public', string $pricing = 'per_person'): RoomType
    {
        return RoomType::create([
            'name' => 'Type ' . uniqid(),
            'base_rate' => 500,
            'pricing_type' => $pricing,
            'room_sharing_type' => $sharing,
            'is_active' => true,
        ]);
    }

    private function createRoom(RoomType $roomType, string $status = 'available', int $capacity = 4): Room
    {
        $floor = Floor::create(['name' => 'Floor ' . uniqid(), 'level' => 1, 'is_active' => true]);

        return Room::create([
            'room_number' => 'R' . uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => $capacity,
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function createReservation(RoomType $roomType, string $status = 'approved'): Reservation
    {
        return Reservation::create([
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now(),
            'check_out_date' => now()->addDays(2),
            'number_of_occupants' => 1,
            'status' => $status,
        ]);
    }

    // ── Dorm capacity edge cases ─────────────────────────────

    public function test_dorm_room_fills_to_capacity(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType, 'available', 2);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'include_primary_in_first_room' => true,
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'guests' => [
                        ['first_name' => 'Guest', 'last_name' => 'Two'],
                    ],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertTrue($result['all_succeeded']);
        $this->assertEquals(2, $result['checked_in_count']);
    }

    public function test_dorm_room_rejects_over_capacity(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType, 'available', 2);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'include_primary_in_first_room' => true,
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'guests' => [
                        ['first_name' => 'Guest', 'last_name' => 'One'],
                        ['first_name' => 'Guest', 'last_name' => 'Two'],
                    ],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertFalse($result['all_succeeded']);
        $this->assertEquals(2, $result['checked_in_count']);
        $this->assertNotEmpty($result['failed_guests']);
    }

    public function test_inactive_room_rejected(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $floor = Floor::create(['name' => 'F1', 'level' => 1, 'is_active' => true]);
        $room = Room::create([
            'room_number' => 'R-INACTIVE',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => 'available',
            'is_active' => false,
        ]);

        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'include_primary_in_first_room' => true,
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'guests' => [
                        ['first_name' => 'Guest', 'last_name' => 'One'],
                    ],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertFalse($result['all_succeeded']);
        $this->assertNotEmpty($result['room_errors']);
    }

    // ── Prepare pending payment ──────────────────────────────

    public function test_prepare_pending_payment_for_private_room(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('private', 'flat_rate');
        $room = $this->createRoom($roomType, 'available', 2);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'include_primary_in_first_room' => true,
            'reservation_rooms' => [
                [
                    'room_mode' => 'private',
                    'room_id' => $room->id,
                    'guests' => [
                        ['first_name' => 'John', 'last_name' => 'Doe'],
                    ],
                ],
            ],
        ];

        $result = $this->service->preparePendingPayment($reservation, $payload);

        $this->assertEquals(1, $result['held_room_count']);
        $this->assertNotNull($result['hold_expires_at']);
        $this->assertEquals('reserved', $room->fresh()->status);
        $this->assertEquals('pending_payment', $reservation->fresh()->status);
    }

    public function test_prepare_pending_payment_rejects_non_approved(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType, 'pending');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only approved reservations');

        $this->service->preparePendingPayment($reservation, [
            'reservation_rooms' => [['room_id' => 1, 'guests' => [['first_name' => 'A', 'last_name' => 'B']]]],
        ]);
    }

    public function test_prepare_pending_payment_rejects_empty_entries(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('at least one room entry');

        $this->service->preparePendingPayment($reservation, ['reservation_rooms' => []]);
    }

    // ── Finalize pending payment ─────────────────────────────

    public function test_finalize_rejects_non_pending_payment(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not in pending payment state');

        $this->service->finalizePendingPayment($reservation, [
            'payment_mode' => 'cash',
            'payment_amount' => 500,
        ]);
    }

    // ── Release expired holds ────────────────────────────────

    public function test_release_expired_holds_restores_rooms(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('private', 'flat_rate');
        $room = $this->createRoom($roomType, 'reserved', 2);
        $reservation = $this->createReservation($roomType);

        $reservation->update([
            'status' => 'pending_payment',
            'checkin_hold_payload' => [
                'payload' => [],
                'entries' => [
                    ['room_mode' => 'private', 'room_id' => $room->id, 'guests' => [['first_name' => 'A', 'last_name' => 'B']]],
                ],
            ],
            'checkin_hold_started_at' => now()->subHours(4),
            'checkin_hold_expires_at' => now()->subHour(),
            'checkin_hold_by' => $user->id,
        ]);

        $released = $this->service->releaseExpiredHolds();

        $this->assertEquals(1, $released);
        $this->assertEquals('available', $room->fresh()->status);
        $this->assertEquals('approved', $reservation->fresh()->status);
    }

    // ── Multiple rooms in one check-in ───────────────────────

    public function test_check_in_multiple_rooms(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room1 = $this->createRoom($roomType, 'available', 4);
        $room2 = $this->createRoom($roomType, 'available', 4);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'include_primary_in_first_room' => true,
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room1->id,
                    'guests' => [
                        ['first_name' => 'Alice', 'last_name' => 'Smith'],
                    ],
                ],
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room2->id,
                    'guests' => [
                        ['first_name' => 'Bob', 'last_name' => 'Jones'],
                    ],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertTrue($result['all_succeeded']);
        // primary (John) + Alice in room1, Bob in room2
        $this->assertEquals(3, $result['checked_in_count']);
        $this->assertEquals('checked_in', $reservation->fresh()->status);
    }

    // ── Gender counting ──────────────────────────────────────

    public function test_gender_counts_tracked_correctly(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType, 'available', 6);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_gender' => 'Male',
            'include_primary_in_first_room' => true,
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'guests' => [
                        ['first_name' => 'Jane', 'last_name' => 'Doe', 'gender' => 'Female'],
                        ['first_name' => 'Bob', 'last_name' => 'Smith', 'gender' => 'Male'],
                    ],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertTrue($result['all_succeeded']);

        $fresh = $reservation->fresh();
        $this->assertEquals('checked_in', $fresh->status);
    }

    // ── Room status after check-in ───────────────────────────

    public function test_private_room_becomes_occupied_after_checkin(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('private', 'flat_rate');
        $room = $this->createRoom($roomType, 'available', 2);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'include_primary_in_first_room' => true,
            'reservation_rooms' => [
                [
                    'room_mode' => 'private',
                    'room_id' => $room->id,
                    'guests' => [],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertTrue($result['all_succeeded']);
        $this->assertEquals('occupied', $room->fresh()->status);
    }
}
