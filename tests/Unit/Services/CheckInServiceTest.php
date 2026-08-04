<?php

namespace Tests\Unit\Services;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomHold;
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

    public function test_complete_onsite_check_in_requires_one_primary_guest_room(): void
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
            'guest_contact_number' => '09171234567',
            'payment_mode' => 'cash',
            'payment_amount' => 1000,
            'payment_or_number' => 'OR-1001',
            'or_date' => now()->toDateString(),
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'includes_primary_guest' => true,
                    'guests' => [],
                ],
            ],
        ];

        $result = $this->service->completeOnsiteCheckIn($reservation, $payload);

        $this->assertTrue($result['all_succeeded']);
        $this->assertEquals(1, $result['checked_in_count']);
    }

    public function test_complete_onsite_check_in_rejects_incomplete_companion_guest_rows(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType, 'available', 4);
        $reservation = $this->createReservation($roomType);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Complete companion guest details for room entry #1, guest #1.');

        $this->service->completeOnsiteCheckIn($reservation, [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_gender' => 'Male',
            'payment_mode' => 'cash',
            'payment_amount' => 1000,
            'payment_or_number' => 'OR-1001',
            'or_date' => now()->toDateString(),
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'includes_primary_guest' => true,
                    'guests' => [
                        [
                            'first_name' => null,
                            'last_name' => null,
                            'gender' => null,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_complete_onsite_check_in_rejects_no_primary_guest_room(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $room = $this->createRoom($roomType, 'available', 4);
        $reservation = $this->createReservation($roomType);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Please choose one room entry to include the primary guest.');

        $this->service->completeOnsiteCheckIn($reservation, [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_gender' => 'Male',
            'payment_mode' => 'cash',
            'payment_amount' => 1000,
            'payment_or_number' => 'OR-1002',
            'or_date' => now()->toDateString(),
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $room->id,
                    'includes_primary_guest' => false,
                    'guests' => [],
                ],
            ],
        ]);
    }

    public function test_complete_onsite_check_in_rejects_multiple_primary_guest_rooms(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('public', 'per_person');
        $roomA = $this->createRoom($roomType, 'available', 4);
        $roomB = $this->createRoom($roomType, 'available', 4);
        $reservation = $this->createReservation($roomType);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Primary guest can only be included in one room entry.');

        $this->service->completeOnsiteCheckIn($reservation, [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_gender' => 'Male',
            'payment_mode' => 'cash',
            'payment_amount' => 1500,
            'payment_or_number' => 'OR-1003',
            'or_date' => now()->toDateString(),
            'reservation_rooms' => [
                [
                    'room_mode' => 'dorm',
                    'room_id' => $roomA->id,
                    'includes_primary_guest' => true,
                    'guests' => [],
                ],
                [
                    'room_mode' => 'dorm',
                    'room_id' => $roomB->id,
                    'includes_primary_guest' => true,
                    'guests' => [
                        ['first_name' => 'Jane', 'last_name' => 'Doe', 'gender' => 'Female'],
                    ],
                ],
            ],
        ]);
    }

    public function test_complete_onsite_check_in_skips_manual_payment_when_balance_is_fully_paid_online(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('private', 'flat_rate');
        $room = $this->createRoom($roomType, 'available', 2);
        $reservation = $this->createReservation($roomType);

        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 1000,
            'payment_mode' => 'PayMongo Online',
            'gateway' => 'paymongo',
            'gateway_payment_id' => 'pay_full_online',
            'gateway_status' => 'paid',
            'is_deposit' => false,
            'status' => 'posted',
        ]);

        $result = $this->service->completeOnsiteCheckIn($reservation, [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_gender' => 'Male',
            'reservation_rooms' => [
                [
                    'room_mode' => 'private',
                    'room_id' => $room->id,
                    'includes_primary_guest' => true,
                    'guests' => [],
                ],
            ],
        ]);

        $this->assertTrue($result['all_succeeded']);
        $this->assertDatabaseMissing('reservation_payments', [
            'reservation_id' => $reservation->id,
            'amount' => 0,
            'payment_mode' => 'online',
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('room_assignments', [
            'reservation_id' => $reservation->id,
            'payment_amount' => 0,
            'payment_or_number' => null,
        ]);
    }

    public function test_complete_onsite_check_in_accepts_the_reservation_own_reserved_room(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('private', 'flat_rate');
        $room = $this->createRoom($roomType, 'reserved', 2);
        $reservation = $this->createReservation($roomType);

        RoomHold::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);

        $result = $this->service->completeOnsiteCheckIn($reservation, [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_gender' => 'Male',
            'payment_mode' => 'cash',
            'payment_amount' => 1000,
            'payment_or_number' => 'OR-HELD-1',
            'or_date' => now()->toDateString(),
            'reservation_rooms' => [[
                'room_mode' => 'private',
                'room_id' => $room->id,
                'includes_primary_guest' => true,
                'guests' => [],
            ]],
        ]);

        $this->assertTrue($result['all_succeeded']);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'checked_in']);
        $this->assertDatabaseHas('room_assignments', ['reservation_id' => $reservation->id, 'room_id' => $room->id]);
        $this->assertDatabaseMissing('room_holds', ['reservation_id' => $reservation->id]);
    }

    public function test_failed_normal_check_in_keeps_its_advance_hold_and_creates_no_partial_assignment(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType('private', 'flat_rate');
        $heldRoom = $this->createRoom($roomType, 'reserved', 2);
        $otherRoom = $this->createRoom($roomType, 'available', 2);
        $reservation = $this->createReservation($roomType);

        RoomHold::create([
            'reservation_id' => $reservation->id,
            'room_id' => $heldRoom->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('selected rooms no longer match');

        try {
            $this->service->completeOnsiteCheckIn($reservation, [
                'guest_first_name' => 'John',
                'guest_last_name' => 'Doe',
                'guest_gender' => 'Male',
                'payment_mode' => 'cash',
                'payment_amount' => 1000,
                'payment_or_number' => 'OR-HELD-2',
                'or_date' => now()->toDateString(),
                'reservation_rooms' => [[
                    'room_mode' => 'private',
                    'room_id' => $otherRoom->id,
                    'includes_primary_guest' => true,
                    'guests' => [],
                ]],
            ]);
        } finally {
            $this->assertDatabaseHas('room_holds', ['reservation_id' => $reservation->id, 'room_id' => $heldRoom->id]);
            $this->assertDatabaseMissing('room_assignments', ['reservation_id' => $reservation->id]);
            $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'approved']);
        }
    }
}
