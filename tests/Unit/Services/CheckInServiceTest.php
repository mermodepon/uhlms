<?php

namespace Tests\Unit\Services;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private CheckInService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CheckInService;

        if (!DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }
    }

    private function createUser(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function createRoomType(string $sharing = 'public', string $pricing = 'per_person'): RoomType
    {
        return RoomType::create([
            'name' => 'Dorm Type ' . uniqid(),
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

    private function createReservation(RoomType $roomType): Reservation
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
            'status' => 'approved',
        ]);
    }

    public function test_execute_checks_in_guests_to_dorm_room(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType, 'available', 4);
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
                        [
                            'first_name' => 'Jane',
                            'last_name' => 'Doe',
                            'gender' => 'Female',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertTrue($result['all_succeeded']);
        $this->assertEquals(2, $result['checked_in_count']); // primary + Jane
        $this->assertEmpty($result['failed_guests']);
        $this->assertEmpty($result['room_errors']);
    }

    public function test_execute_checks_in_guests_to_private_room(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('private', 'flat_rate');
        $room = $this->createRoom($roomType, 'available', 2);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_gender' => 'Male',
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
        $this->assertEquals(1, $result['checked_in_count']);
    }

    public function test_execute_fails_when_room_not_available(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType, 'maintenance', 4);
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
                        ['first_name' => 'Jane', 'last_name' => 'Doe'],
                    ],
                ],
            ],
        ];

        $result = $this->service->execute($reservation, $payload);

        $this->assertFalse($result['all_succeeded']);
        $this->assertNotEmpty($result['room_errors']);
    }

    public function test_execute_fails_when_no_guests_provided(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType);

        $payload = [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'include_primary_in_first_room' => false,
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'guests' => [],
                    'includes_primary_guest' => false,
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->service->execute($reservation, $payload);
    }

    public function test_prepare_pending_payment_only_for_approved(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType);
        $reservation->update(['status' => 'pending']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only approved reservations can be prepared for payment.');
        $this->service->preparePendingPayment($reservation, []);
    }

    public function test_prepare_pending_payment_requires_room_entries(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Please add at least one room entry');
        $this->service->preparePendingPayment($reservation, ['reservation_rooms' => []]);
    }

    public function test_prepare_pending_payment_locks_room(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType);
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
                        ['first_name' => 'Jane', 'last_name' => 'Doe'],
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

    public function test_release_pending_payment_hold(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType);
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
                        ['first_name' => 'Jane', 'last_name' => 'Doe'],
                    ],
                ],
            ],
        ];

        $this->service->preparePendingPayment($reservation, $payload);
        $reservation->refresh();

        $this->service->releasePendingPaymentHold($reservation, true);

        $this->assertEquals('available', $room->fresh()->status);
        $this->assertEquals('approved', $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->checkin_hold_payload);
    }

    public function test_finalize_pending_payment_requires_pending_payment_status(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType();
        $reservation = $this->createReservation($roomType);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reservation is not in pending payment state.');
        $this->service->finalizePendingPayment($reservation, []);
    }

    public function test_validate_payment_data_rejects_empty_mode(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType);
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
                        ['first_name' => 'Jane', 'last_name' => 'Doe'],
                    ],
                ],
            ],
        ];

        $this->service->preparePendingPayment($reservation, $payload);
        $reservation->refresh();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mode of payment is required');
        $this->service->finalizePendingPayment($reservation, ['payment_mode' => '']);
    }

    public function test_release_expired_holds(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType);
        $reservation = $this->createReservation($roomType);

        // Simulate an expired hold
        $reservation->update([
            'status' => 'pending_payment',
            'checkin_hold_payload' => [
                'payload' => [],
                'entries' => [
                    ['room_id' => $room->id, 'room_mode' => 'dorm', 'guests' => []],
                ],
            ],
            'checkin_hold_started_at' => now()->subHours(4),
            'checkin_hold_expires_at' => now()->subHour(),
            'checkin_hold_by' => $user->id,
        ]);

        $room->update(['status' => 'reserved']);

        $released = $this->service->releaseExpiredHolds();

        $this->assertEquals(1, $released);
        $this->assertEquals('available', $room->fresh()->status);
        $this->assertEquals('approved', $reservation->fresh()->status);
    }
}
