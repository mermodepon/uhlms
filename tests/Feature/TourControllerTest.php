<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\GuestAccount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\TourWaypoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TourControllerTest extends TestCase
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

    private function createRoomType(array $overrides = []): RoomType
    {
        return RoomType::create(array_merge([
            'name' => 'Tour Room '.uniqid(),
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ], $overrides));
    }

    private function createRoom(RoomType $roomType): Room
    {
        $floor = Floor::firstOrCreate(
            ['name' => 'Tour Floor'],
            ['level' => 1, 'is_active' => true]
        );

        return Room::create([
            'room_number' => 'TR'.uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    private function createStaffUser(): User
    {
        return User::create([
            'name' => 'Tour Staff '.uniqid(),
            'email' => 'tour-staff-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    private function createGuestAccount(array $overrides = []): GuestAccount
    {
        return GuestAccount::create(array_merge([
            'first_name' => 'Tour',
            'last_name' => 'Account',
            'email' => 'tour-account-'.uniqid().'@example.com',
            'phone' => '09171234567',
            'age' => 27,
            'gender' => 'Other',
            'address' => 'Musuan, Maramag, Bukidnon',
            'password' => 'password',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function createActiveWaypoint(): void
    {
        TourWaypoint::create([
            'name' => 'Tour Test Scene '.uniqid(),
            'type' => 'common-area',
            'panorama_image' => 'virtual-tour/panoramas/test.jpg',
            'position_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_virtual_tour_prefills_the_authenticated_guest_profile(): void
    {
        $account = $this->createGuestAccount();
        $this->createActiveWaypoint();

        $this->actingAs($account, 'guest')
            ->get(route('guest.tour.viewer'))
            ->assertOk()
            ->assertSee('name="guest_first_name" value="Tour"', false)
            ->assertSee('name="guest_last_name" value="Account"', false)
            ->assertSee('name="guest_email" value="'.$account->email.'"', false)
            ->assertSee('name="guest_phone" value="09171234567"', false)
            ->assertSee('name="guest_age" value="27"', false)
            ->assertSee('<option value="Other" selected', false)
            ->assertSee('Musuan, Maramag, Bukidnon');
    }

    public function test_virtual_tour_leaves_personal_fields_blank_for_visitors(): void
    {
        $this->createActiveWaypoint();

        $this->get(route('guest.tour.viewer'))
            ->assertOk()
            ->assertSee('name="guest_first_name" value=""', false)
            ->assertSee('name="guest_email" value=""', false)
            ->assertDontSee('<option value="Male" selected', false)
            ->assertDontSee('<option value="Female" selected', false)
            ->assertDontSee('<option value="Other" selected', false);
    }

    public function test_virtual_tour_reservation_with_an_edited_email_is_not_linked_to_the_guest_account(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);
        $account = $this->createGuestAccount();
        $roomType = $this->createRoomType();
        $this->createRoom($roomType);

        $this->actingAs($account, 'guest')
            ->postJson(route('api.tour.reserve'), [
                'guest_first_name' => 'Other',
                'guest_last_name' => 'Guest',
                'guest_email' => 'other-guest@example.com',
                'guest_phone' => '09179876543',
                'guest_age' => 30,
                'guest_gender' => 'Female',
                'preferred_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDay()->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'number_of_occupants' => 1,
                'source' => 'virtual_tour',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('reservations', [
            'guest_email' => 'other-guest@example.com',
            'guest_first_name' => 'Other',
            'guest_account_id' => null,
        ]);
    }

    private function occupyRoomForDates(RoomType $roomType, string $checkInDate, string $checkOutDate): Room
    {
        $user = $this->createStaffUser();
        $room = $this->createRoom($roomType);
        $reservation = Reservation::create([
            'guest_first_name' => 'Booked',
            'guest_last_name' => 'Guest',
            'guest_email' => 'occupied-'.uniqid().'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'number_of_occupants' => 1,
            'status' => 'checked_in',
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        return $room;
    }

    public function test_virtual_tour_reservation_requires_acknowledgement_when_availability_looks_unavailable(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType(['name' => 'Tour Suite']);
        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();
        $this->occupyRoomForDates($roomType, $checkIn, $checkOut);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-warning@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'number_of_occupants' => 1,
            'source' => 'virtual_tour',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_availability_confirmation', true);
    }

    public function test_virtual_tour_reservation_submit_stores_room_type_only(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType(['name' => 'Tour Suite']);
        $room = $this->createRoom($roomType);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'preferred_room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 2,
            'special_requests' => 'Near the lobby please.',
            'availability_acknowledged' => 1,
            'source' => 'virtual_tour',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $reservation = Reservation::where('guest_email', 'tour@example.com')->first();

        $this->assertNotNull($reservation);
        $this->assertSame($roomType->id, $reservation->preferred_room_type_id);
        $this->assertDatabaseHas('reservation_room_requests', [
            'reservation_id' => $reservation->id,
            'room_type_id' => $roomType->id,
            'requested_room_count' => 1,
            'occupant_count' => 2,
        ]);
        $this->assertStringContainsString('Reservation request submitted via Virtual Tour', (string) $reservation->special_requests);
        $this->assertStringNotContainsString((string) $room->id, (string) $reservation->special_requests);
        $this->assertStringNotContainsString('Availability warning acknowledged by guest', (string) $reservation->special_requests);
    }

    public function test_virtual_tour_reservation_allows_acknowledged_request_despite_low_availability(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType(['name' => 'Tour Suite']);
        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();
        $this->occupyRoomForDates($roomType, $checkIn, $checkOut);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-ack@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'number_of_occupants' => 1,
            'availability_acknowledged' => 1,
            'source' => 'virtual_tour',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $reservation = Reservation::where('guest_email', 'tour-ack@example.com')->first();
        $this->assertNotNull($reservation);
        $this->assertStringContainsString('Availability warning acknowledged by guest', (string) $reservation->special_requests);
    }

    public function test_virtual_tour_reservation_rejects_primary_guest_younger_than_eighteen(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType(['name' => 'Tour Suite']);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-underage@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 17,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'source' => 'virtual_tour',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('guest_age');
    }

    public function test_virtual_tour_reservation_rejects_missing_required_mobile_number(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType(['name' => 'Tour Suite']);
        $this->createRoom($roomType);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-missing-mobile@example.com',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'availability_acknowledged' => 1,
            'source' => 'virtual_tour',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('guest_phone');
    }

    public function test_virtual_tour_reservation_rejects_invalid_mobile_number(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType(['name' => 'Tour Suite']);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-invalid-mobile@example.com',
            'guest_phone' => 'wewe',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'source' => 'virtual_tour',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('guest_phone');
    }

    public function test_virtual_tour_reservation_rejects_private_room_occupants_above_capacity(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType(['name' => 'Tour Capacity Limited Private Room']);
        $this->createRoom($roomType);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-private-capacity@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 5,
            'source' => 'virtual_tour',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('number_of_occupants');
    }

    public function test_virtual_tour_reservation_rejects_public_room_occupants_above_available_beds(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType([
            'name' => 'Tour Dormitory Bed Limit',
            'room_sharing_type' => 'public',
        ]);
        $this->createRoom($roomType);
        $this->createRoom($roomType);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-dorm-nine@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 9,
            'source' => 'virtual_tour',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('number_of_occupants');
    }

    public function test_virtual_tour_reservation_accepts_public_room_occupants_up_to_available_beds(): void
    {
        $this->withoutMiddleware(\Spatie\Honeypot\ProtectAgainstSpam::class);

        $roomType = $this->createRoomType([
            'name' => 'Tour Dormitory Available Beds',
            'room_sharing_type' => 'public',
        ]);
        $this->createRoom($roomType);
        $this->createRoom($roomType);

        $response = $this->postJson(route('api.tour.reserve'), [
            'guest_first_name' => 'Tour',
            'guest_last_name' => 'Guest',
            'guest_email' => 'tour-dorm-eight@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'guest_gender' => 'Male',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 8,
            'availability_acknowledged' => 1,
            'source' => 'virtual_tour',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
