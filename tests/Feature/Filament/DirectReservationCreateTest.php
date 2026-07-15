<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ReservationResource\Pages\CreateReservation;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectReservationCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_confirmed_multi_capacity_reservation_with_exact_rooms_held(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'permissions' => null,
        ]);

        $roomType = RoomType::create([
            'name' => 'Family',
            'base_rate' => 1200,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $floor = Floor::create([
            'name' => 'First Floor',
            'level' => 1,
            'is_active' => true,
        ]);

        $familyThree = Room::create([
            'room_number' => 'F-3',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 3,
            'status' => 'available',
            'is_active' => true,
        ]);

        $familyFour = Room::create([
            'room_number' => 'F-4',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $page = app(CreateReservation::class);
        $data = $this->callProtected($page, 'mutateFormDataBeforeCreate', [[
                'guest_last_name' => 'Santos',
                'guest_first_name' => 'Maria',
                'guest_age' => 30,
                'guest_email' => 'maria.santos@example.com',
                'guest_phone' => '09171234567',
                'guest_gender' => 'Female',
                'check_in_date' => now()->addDays(5)->toDateString(),
                'check_out_date' => now()->addDays(7)->toDateString(),
                'direct_room_assignments' => [
                    [
                        'room_type_id' => $roomType->id,
                        'requested_capacity' => 3,
                        'occupant_count' => 3,
                        'room_ids' => [$familyThree->id],
                    ],
                    [
                        'room_type_id' => $roomType->id,
                        'requested_capacity' => 4,
                        'occupant_count' => 4,
                        'room_ids' => [$familyFour->id],
                    ],
                ],
            ]]);
        $this->callProtected($page, 'handleRecordCreation', [$data]);
        $reservation = Reservation::query()->firstOrFail();

        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame(7, (int) $reservation->number_of_occupants);
        $this->assertSame($roomType->id, $reservation->preferred_room_type_id);
        $this->assertCount(2, $reservation->roomRequests);
        $this->assertSame([3, 4], $reservation->roomRequests->pluck('requested_capacity')->all());
        $this->assertSame(2, $reservation->roomHolds()->count());
        $this->assertDatabaseHas('room_holds', ['room_id' => $familyThree->id, 'reservation_id' => $reservation->id]);
        $this->assertDatabaseHas('room_holds', ['room_id' => $familyFour->id, 'reservation_id' => $reservation->id]);
    }

    public function test_staff_cannot_select_the_same_room_on_two_assignment_lines(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'permissions' => null,
        ]);

        $roomType = RoomType::create([
            'name' => 'Deluxe',
            'base_rate' => 1200,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $floor = Floor::create([
            'name' => 'First Floor',
            'level' => 1,
            'is_active' => true,
        ]);
        $room = Room::create([
            'room_number' => 'D-1',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $page = app(CreateReservation::class);

        try {
            $this->callProtected($page, 'mutateFormDataBeforeCreate', [[
                'guest_last_name' => 'Santos',
                'guest_first_name' => 'Maria',
                'guest_age' => 30,
                'guest_email' => 'maria.santos@example.com',
                'guest_phone' => '09171234567',
                'guest_gender' => 'Female',
                'check_in_date' => now()->addDays(5)->toDateString(),
                'check_out_date' => now()->addDays(7)->toDateString(),
                'direct_room_assignments' => [
                    ['room_type_id' => $roomType->id, 'requested_capacity' => 2, 'occupant_count' => 1, 'room_ids' => [$room->id]],
                    ['room_type_id' => $roomType->id, 'requested_capacity' => 2, 'occupant_count' => 1, 'room_ids' => [$room->id]],
                ],
            ]]);
            $this->fail('Expected duplicate room validation to fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('direct_room_assignments.1.room_ids', $exception->errors());
        }

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('room_holds', 0);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function callProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
