<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReservationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestPaymentControllerTest extends TestCase
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

        Setting::create(['key' => 'online_payments_enabled', 'value' => '1']);
        Setting::create(['key' => 'default_deposit_percentage', 'value' => '30']);
    }

    private function createRoomType(): RoomType
    {
        return RoomType::create([
            'name' => 'Standard '.uniqid(),
            'base_rate' => 1000,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
    }

    private function createReservation(string $status = 'pending'): Reservation
    {
        return Reservation::create([
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => 'john-'.uniqid().'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $this->createRoomType()->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'status' => $status,
        ]);
    }

    private function createStaffUser(): User
    {
        return User::create([
            'name' => 'Staff User',
            'email' => 'staff-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    public function test_pending_reservation_payment_page_is_not_available(): void
    {
        $reservation = $this->createReservation('pending');

        $response = $this->get(route('guest.payment.show', ['token' => $reservation->payment_link_token]));

        $response->assertNotFound();
    }

    public function test_approved_reservation_payment_page_is_available(): void
    {
        $reservation = $this->createReservation('approved');
        $reservation->update(['approved_at' => now()]);

        $response = $this->get(route('guest.payment.show', ['token' => $reservation->payment_link_token]));

        $response->assertOk();
        $response->assertSee('Complete Your Payment');
    }

    public function test_approval_rotates_stale_payment_token_before_guest_payment_opens(): void
    {
        $reservation = $this->createReservation('pending');
        $staleToken = (string) Str::uuid();

        $reservation->update([
            'payment_link_token' => $staleToken,
            'payment_link_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($this->createStaffUser());
        app(ReservationWorkflowService::class)->approve($reservation);

        $fresh = $reservation->fresh();

        $this->assertNotSame($staleToken, $fresh->payment_link_token);
        $this->get(route('guest.payment.show', ['token' => $staleToken]))->assertNotFound();
        $this->get(route('guest.payment.show', ['token' => $fresh->payment_link_token]))->assertOk();
    }
}
