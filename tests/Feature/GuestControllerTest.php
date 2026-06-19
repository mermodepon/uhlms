<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\TourWaypoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GuestControllerTest extends TestCase
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
            'name' => 'Standard Room ' . uniqid(),
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ], $overrides));
    }

    private function createRoom(RoomType $roomType, string $status = 'available'): Room
    {
        $floor = Floor::firstOrCreate(
            ['name' => 'Ground Floor'],
            ['level' => 1, 'is_active' => true]
        );

        return Room::create([
            'room_number' => 'R' . uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function createReservationForRoomType(RoomType $roomType, array $overrides = []): Reservation
    {
        return Reservation::create(array_merge([
            'guest_first_name' => 'Booked',
            'guest_last_name' => 'Guest',
            'guest_email' => 'booked-'.uniqid().'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'checked_in',
        ], $overrides));
    }

    private function occupyRoomForDates(RoomType $roomType, string $checkInDate, string $checkOutDate): Room
    {
        $user = $this->createStaffUser();
        $room = $this->createRoom($roomType, 'occupied');
        $reservation = $this->createReservationForRoomType($roomType, [
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'number_of_occupants' => 1,
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

    private function createStaffUser(): User
    {
        return User::create([
            'name' => 'Staff '.uniqid(),
            'email' => 'staff-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    // ── Home Page ────────────────────────────────────────────

    public function test_home_page_returns_200(): void
    {
        $this->createRoomType();

        $response = $this->get(route('guest.home'));
        $response->assertStatus(200);
    }

    public function test_home_page_displays_active_room_types(): void
    {
        $active = $this->createRoomType(['name' => 'Active Suite', 'is_active' => true]);
        $inactive = $this->createRoomType(['name' => 'Hidden Room', 'is_active' => false]);

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertSee('Active Suite');
        $response->assertDontSee('Hidden Room');
    }

    public function test_home_page_uses_guest_site_settings(): void
    {
        $this->createRoomType();
        Setting::set('guest_site_title', 'Custom Homestay');
        Setting::set('guest_hero_headline', 'Stay at the Campus Lodge');
        Setting::set('guest_hero_message', 'A configurable public welcome message.');
        Setting::set('guest_announcement_enabled', '1');
        Setting::set('guest_announcement_text', 'Public notice for guests.');
        Setting::set('guest_show_booking_policy', '1');
        Setting::set('guest_booking_policy', 'Bring a valid ID during check-in.');
        Setting::set('guest_show_faq', '1');
        Setting::set('guest_faq_items', json_encode([
            ['question' => 'Do you accept walk-ins?', 'answer' => 'Please submit a request first.'],
        ]));

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertSee('Stay at the Campus Lodge');
        $response->assertSee('A configurable public welcome message.');
        $response->assertSee('Public notice for guests.');
        $response->assertSee('Bring a valid ID during check-in.');
        $response->assertSee('Do you accept walk-ins?');
    }

    public function test_home_page_keeps_gradient_fallback_without_enabled_hero_background(): void
    {
        $this->createRoomType();
        Setting::set('guest_hero_background_image', 'site-settings/hero/lobby.jpg');
        Setting::set('guest_hero_background_enabled', '0');

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertDontSee('/storage/site-settings/hero/lobby.jpg', false);
    }

    public function test_home_page_renders_enabled_hero_background_image(): void
    {
        $this->createRoomType();
        Setting::set('guest_hero_background_image', 'site-settings/hero/lobby.jpg');
        Setting::set('guest_hero_background_enabled', '1');
        Setting::set('guest_hero_background_opacity', '82');

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertSee('/storage/site-settings/hero/lobby.jpg', false);
        $response->assertSee('absolute inset-0 h-full w-full object-cover', false);
        $response->assertSee('opacity: 0.59', false);
        $response->assertSee('rgba(0, 35, 14, 0.82)', false);
        $response->assertSee('backdrop-filter: blur(18px)', false);
    }

    // ── Rooms Catalog ────────────────────────────────────────

    public function test_rooms_page_returns_200(): void
    {
        $response = $this->get(route('guest.rooms'));
        $response->assertStatus(200);
    }

    public function test_rooms_page_shows_only_active_room_types(): void
    {
        $this->createRoomType(['name' => 'Visible Dorm', 'is_active' => true]);
        $this->createRoomType(['name' => 'Inactive Dorm', 'is_active' => false]);

        $response = $this->get(route('guest.rooms'));

        $response->assertSee('Visible Dorm');
        $response->assertDontSee('Inactive Dorm');
    }

    public function test_rooms_page_keeps_shared_room_type_available_when_remaining_beds_cover_requested_guests(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType([
            'name' => 'Dormitory',
            'room_sharing_type' => 'public',
        ]);

        $room = $this->createRoom($roomType);
        $room->update(['capacity' => 8]);

        $reservation = $this->createReservationForRoomType($roomType, [
            'check_in_date' => '2026-04-29',
            'check_out_date' => '2026-04-30',
            'number_of_occupants' => 1,
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 2,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomTypes', function ($roomTypes) use ($roomType) {
            $dormitory = $roomTypes->firstWhere('id', $roomType->id);

            return $dormitory
                && $dormitory->available_beds_count === 7
                && $dormitory->total_beds_count === 8
                && $dormitory->can_accommodate_requested_guests === true;
        });
    }

    public function test_current_shared_room_availability_counts_open_beds_in_reserved_dorms(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType([
            'name' => 'Dormitory',
            'room_sharing_type' => 'public',
        ]);

        $reservedDorm = $this->createRoom($roomType);
        $reservedDorm->update([
            'capacity' => 20,
            'status' => 'reserved',
        ]);

        $openDorm = $this->createRoom($roomType);
        $openDorm->update([
            'capacity' => 20,
            'status' => 'available',
        ]);

        $reservation = $this->createReservationForRoomType($roomType, [
            'number_of_occupants' => 5,
        ]);

        foreach (range(1, 4) as $index) {
            RoomAssignment::create([
                'reservation_id' => $reservation->id,
                'room_id' => $reservedDorm->id,
                'status' => 'checked_in',
                'checked_in_at' => now(),
                'assigned_by' => $user->id,
            ]);
        }

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $openDorm->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertViewHas('roomType', function (RoomType $dormitory): bool {
            return $dormitory->available_beds_count === 35
                && $dormitory->total_beds_count === 40
                && $dormitory->available_rooms_count === 2;
        });
    }

    public function test_rooms_page_marks_shared_room_type_unavailable_when_requested_guests_exceed_remaining_beds(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType([
            'name' => 'Dormitory',
            'room_sharing_type' => 'public',
        ]);

        $room = $this->createRoom($roomType);
        $room->update(['capacity' => 8]);

        $reservation = $this->createReservationForRoomType($roomType, [
            'check_in_date' => '2026-04-29',
            'check_out_date' => '2026-04-30',
            'number_of_occupants' => 1,
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 8,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomTypes', function ($roomTypes) use ($roomType) {
            $dormitory = $roomTypes->firstWhere('id', $roomType->id);

            return $dormitory
                && $dormitory->available_beds_count === 7
                && $dormitory->can_accommodate_requested_guests === false;
        });
    }

    public function test_rooms_page_keeps_private_room_type_unavailable_when_requested_guests_exceed_room_capacity(): void
    {
        $roomType = $this->createRoomType([
            'name' => 'Standard Room',
            'room_sharing_type' => 'private',
        ]);

        $room = $this->createRoom($roomType);
        $room->update(['capacity' => 2]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 3,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomTypes', function ($roomTypes) use ($roomType) {
            $private = $roomTypes->firstWhere('id', $roomType->id);

            return $private
                && $private->available_rooms_count === 1
                && $private->can_accommodate_requested_guests === false;
        });
    }

    // ── Room Detail ──────────────────────────────────────────

    public function test_room_detail_page_returns_200(): void
    {
        $roomType = $this->createRoomType();
        $this->createRoom($roomType);

        $response = $this->get(route('guest.room-detail', $roomType));
        $response->assertStatus(200);
    }

    public function test_room_detail_shows_room_info(): void
    {
        $roomType = $this->createRoomType(['name' => 'Deluxe Suite']);
        $room = $this->createRoom($roomType);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertSee('Deluxe Suite');
    }

    public function test_room_detail_links_to_matching_room_tour_scene_when_available(): void
    {
        $roomType = $this->createRoomType(['name' => 'Deluxe Suite']);
        $this->createRoom($roomType);

        TourWaypoint::create([
            'name' => 'Deluxe Suite Door',
            'slug' => 'deluxe-suite-door',
            'type' => 'room-door',
            'panorama_image' => 'virtual-tour/panoramas/deluxe-door.jpg',
            'position_order' => 2,
            'linked_room_type_id' => $roomType->id,
            'is_active' => true,
        ]);

        TourWaypoint::create([
            'name' => 'Deluxe Suite Interior',
            'slug' => 'deluxe-suite-interior',
            'type' => 'room-interior',
            'panorama_image' => 'virtual-tour/panoramas/deluxe-interior.jpg',
            'position_order' => 3,
            'linked_room_type_id' => $roomType->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertSee(route('guest.tour.viewer', ['slug' => 'deluxe-suite-interior'], false), false);
        $response->assertSee('View This Room in 360', false);
    }

    public function test_room_detail_falls_back_to_default_tour_when_no_matching_scene_exists(): void
    {
        $roomType = $this->createRoomType(['name' => 'Economy Room']);
        $this->createRoom($roomType);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertSee(route('guest.tour.viewer', [], false), false);
        $response->assertSee('Start Virtual Tour', false);
    }

    // ── Virtual Tours ────────────────────────────────────────

    public function test_virtual_tours_page_returns_200(): void
    {
        $response = $this->get(route('guest.virtual-tours'));
        $response->assertRedirect(route('guest.tour.viewer'));
    }

    // ── Reserve Form ─────────────────────────────────────────

    public function test_reserve_form_returns_200(): void
    {
        $response = $this->get(route('guest.reserve'));
        $response->assertStatus(200);
    }

    // ── Reserve Submit ───────────────────────────────────────

    public function test_reserve_submit_creates_reservation(): void
    {
        $roomType = $this->createRoomType();
        $this->createRoom($roomType);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 2,
        ]);

        $response->assertRedirect(route('guest.track'));
        $this->assertDatabaseHas('reservations', [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'status' => 'pending',
        ]);
    }

    public function test_reserve_submit_validates_required_fields(): void
    {
        $response = $this->post(route('guest.reserve.submit'), []);

        $response->assertSessionHasErrors([
            'guest_last_name',
            'guest_first_name',
            'guest_gender',
            'guest_email',
            'preferred_room_type_id',
            'check_in_date',
            'check_out_date',
            'number_of_occupants',
        ]);
    }

    public function test_reserve_submit_requires_acknowledgement_when_availability_looks_unavailable(): void
    {
        $roomType = $this->createRoomType();
        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();
        $this->occupyRoomForDates($roomType, $checkIn, $checkOut);

        $response = $this->from(route('guest.reserve'))->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'number_of_occupants' => 1,
        ]);

        $response->assertRedirect(route('guest.reserve'));
        $response->assertSessionHasErrors('availability_acknowledged');
        $this->assertDatabaseMissing('reservations', [
            'guest_email' => 'john@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_reserve_submit_allows_acknowledged_request_despite_low_availability(): void
    {
        $roomType = $this->createRoomType();
        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();
        $this->occupyRoomForDates($roomType, $checkIn, $checkOut);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john-ack@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'number_of_occupants' => 1,
            'availability_acknowledged' => '1',
        ]);

        $response->assertRedirect(route('guest.track'));

        $reservation = Reservation::where('guest_email', 'john-ack@example.com')->first();
        $this->assertNotNull($reservation);
        $this->assertStringContainsString('Availability warning acknowledged by guest', (string) $reservation->special_requests);
    }

    public function test_reserve_submit_rejects_past_check_in_date(): void
    {
        $roomType = $this->createRoomType();

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'number_of_occupants' => 1,
        ]);

        $response->assertSessionHasErrors('check_in_date');
    }

    public function test_reserve_submit_rejects_checkout_before_checkin(): void
    {
        $roomType = $this->createRoomType();

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'number_of_occupants' => 1,
        ]);

        $response->assertSessionHasErrors('check_out_date');
    }

    // ── Track Reservation ────────────────────────────────────

    public function test_track_page_returns_200(): void
    {
        $response = $this->get(route('guest.track'));
        $response->assertStatus(200);
    }

    public function test_track_finds_reservation_by_reference_and_guest_email(): void
    {
        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'pending',
        ]);

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertSee($reservation->reference_number);
    }

    public function test_track_shows_nothing_for_invalid_reference(): void
    {
        $response = $this->get(route('guest.track', [
            'reference' => 'INVALID-REF',
            'guest_email' => 'guest@example.com',
        ]));

        $response->assertStatus(200);
    }

    public function test_track_requires_guest_email_for_manual_lookup(): void
    {
        $response = $this->get(route('guest.track', [
            'reference' => '2026-0001',
        ]));

        $response->assertSessionHasErrors('guest_email');
    }

    public function test_secure_track_link_allows_guest_lookup_without_manual_email_entry(): void
    {
        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'pending',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'guest.track.secure',
            now()->addMinutes(30),
            ['reservation' => $reservation->id]
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertSee($reservation->reference_number);
    }

    public function test_track_expires_old_checked_out_reservations(): void
    {
        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Old',
            'guest_last_name' => 'Guest',
            'guest_email' => 'old@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->subDays(40),
            'check_out_date' => now()->subDays(35),
            'number_of_occupants' => 1,
            'status' => 'checked_out',
        ]);

        // Backdate the updated_at to 31 days ago
        Reservation::where('id', $reservation->id)->update(['updated_at' => now()->subDays(31)]);

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Tracking period has ended');
        $response->assertDontSee($reservation->reference_number.' status');
    }

    public function test_track_hides_payment_link_for_pending_requests(): void
    {
        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Pending',
            'guest_last_name' => 'Guest',
            'guest_email' => 'pending@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'pending',
        ]);

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee('Pay Deposit Now');
    }

    public function test_track_shows_payment_link_for_approved_requests(): void
    {
        \App\Models\Setting::create(['key' => 'online_payments_enabled', 'value' => '1']);
        \App\Models\Setting::create(['key' => 'default_deposit_percentage', 'value' => '30']);

        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Approved',
            'guest_last_name' => 'Guest',
            'guest_email' => 'approved@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Pay Deposit Now');
    }
}
