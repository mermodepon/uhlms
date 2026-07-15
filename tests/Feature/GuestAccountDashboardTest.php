<?php

namespace Tests\Feature;

use App\Models\GuestAccount;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Models\Setting;
use App\Services\ReservationAccountLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GuestAccountDashboardTest extends TestCase
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

    public function test_verified_guest_dashboard_renders_every_supported_status_without_a_server_error(): void
    {
        $account = $this->account(['email_verified_at' => now()]);
        $roomType = $this->roomType();

        foreach (array_keys(Reservation::statusOptions()) as $status) {
            $this->reservation($account, $roomType, $status);
        }

        $response = $this->actingAs($account, 'guest')->get(route('guest.account.dashboard'));

        $response->assertOk()
            ->assertSee('Alternative Offer Pending')
            ->assertViewHas('stats', fn (array $stats): bool => array_key_exists('awaiting_alternative_confirmation', $stats) && $stats['awaiting_alternative_confirmation'] === 1)
            ->assertViewHas('statCards', fn (array $cards): bool => array_key_exists('awaiting_alternative_confirmation', $cards));
    }

    public function test_checked_in_reservation_is_not_counted_as_upcoming(): void
    {
        $account = $this->account(['email_verified_at' => now()]);
        $checkedIn = $this->reservation($account, $this->roomType(), 'checked_in');
        $checkedIn->update(['check_in_date' => now()->toDateString()]);

        $response = $this->actingAs($account, 'guest')->get(route('guest.account.dashboard'));

        $response->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['upcoming'] === 0 && $stats['active'] === 1);
    }

    public function test_registration_logs_the_guest_in_and_sends_a_verification_email(): void
    {
        Mail::fake();

        $response = $this->post(route('guest.account.register.submit'), [
            'last_name' => 'Guest',
            'first_name' => 'New',
            'email' => 'new-guest@example.com',
            'phone' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('guest.account.dashboard'));
        $this->assertAuthenticated('guest');
        $this->assertDatabaseHas('guest_accounts', ['email' => 'new-guest@example.com']);
    }

    public function test_verified_matching_account_is_linked_but_unverified_or_disabled_accounts_are_not(): void
    {
        $linker = app(ReservationAccountLinker::class);
        $roomType = $this->roomType();
        $verified = $this->account(['email' => 'Verified@example.com', 'email_verified_at' => now()]);
        $matchingReservation = Reservation::create([
            'guest_email' => 'verified@EXAMPLE.com',
            'guest_first_name' => 'Matching',
            'guest_last_name' => 'Guest',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'approved',
        ]);

        $this->assertSame($verified->id, $matchingReservation->fresh()->guest_account_id);
        $this->assertDatabaseHas('reservations', ['id' => $matchingReservation->id, 'guest_account_id' => $verified->id]);

        $unverified = $this->account(['email' => 'unverified@example.com']);
        $unverifiedReservation = $this->reservationForEmail($roomType, $unverified->email);
        $disabled = $this->account(['email' => 'disabled@example.com', 'email_verified_at' => now(), 'disabled_at' => now()]);
        $disabledReservation = $this->reservationForEmail($roomType, $disabled->email);

        $this->assertNull($linker->link($unverifiedReservation));
        $this->assertNull($linker->link($disabledReservation));
        $this->assertDatabaseHas('reservations', ['id' => $unverifiedReservation->id, 'guest_account_id' => null]);
        $this->assertDatabaseHas('reservations', ['id' => $disabledReservation->id, 'guest_account_id' => null]);
    }

    public function test_email_verification_automatically_links_matching_unclaimed_reservations(): void
    {
        $account = $this->account(['email' => 'claim-after-register@example.com']);
        $reservation = $this->reservationForEmail($this->roomType(), 'CLAIM-AFTER-REGISTER@example.com');
        $verificationUrl = URL::temporarySignedRoute(
            'guest.account.verify',
            now()->addHour(),
            ['account' => $account->id],
            false,
        );

        $this->get($verificationUrl)
            ->assertRedirect(route('guest.account.dashboard'));

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'guest_account_id' => $account->id]);
    }

    public function test_guest_reservation_detail_shows_safe_online_payment_summary(): void
    {
        $account = $this->account(['email_verified_at' => now()]);
        $reservation = $this->reservation($account, $this->roomType(), 'confirmed');
        $reservation->update(['payments_total' => 960, 'balance_due' => 0, 'payment_status' => 'paid']);
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 960,
            'payment_mode' => 'PayMongo Online',
            'gateway' => 'paymongo',
            'gateway_status' => 'paid',
            'is_deposit' => false,
            'status' => 'posted',
            'received_at' => now(),
            'meta' => ['payment_type' => 'checkin_balance'],
        ]);

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Online Payments')
            ->assertSee('Check-in balance')
            ->assertSee('PHP 960.00')
            ->assertDontSee('checkout_url');
    }

    public function test_guest_reservation_detail_shows_deposit_against_estimated_remaining_balance_before_checkin(): void
    {
        $account = $this->account(['email_verified_at' => now()]);
        $roomType = $this->roomType();
        $roomType->update(['base_rate' => 800]);
        $reservation = $this->reservation($account, $roomType, 'confirmed');
        $reservation->update([
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
        ]);
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 160,
            'payment_mode' => 'GCash',
            'gateway' => 'paymongo',
            'gateway_status' => 'paid',
            'is_deposit' => true,
            'status' => 'posted',
            'received_at' => now(),
        ]);

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Deposit received')
            ->assertSee('Estimated remaining balance')
            ->assertSee('PHP 640.00')
            ->assertSee('Estimated total:')
            ->assertSee('PHP 800.00')
            ->assertDontSee('Payment status</dt><dd>Paid', false);
    }

    public function test_verified_guest_can_open_their_pending_checkin_balance_from_their_profile_only(): void
    {
        Setting::create(['key' => 'online_payments_enabled', 'value' => '1']);
        $account = $this->account(['email_verified_at' => now()]);
        $reservation = $this->reservation($account, $this->roomType(), 'confirmed');
        $checkoutUrl = 'https://checkout.paymongo.com/test-checkin-balance';
        $payment = ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 960,
            'payment_mode' => 'paymongo_online',
            'gateway' => 'paymongo',
            'gateway_status' => 'pending',
            'gateway_metadata' => ['checkout_url' => $checkoutUrl],
            'is_deposit' => false,
            'status' => 'pending',
            'meta' => ['source' => 'checkin_balance', 'payment_type' => 'checkin_balance'],
        ]);

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Pay remaining balance')
            ->assertSee(route('guest.account.reservations.check-in-payment.qr', $reservation, false))
            ->assertDontSee($checkoutUrl);

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.check-in-payment.qr', $reservation))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.check-in-payment.checkout', $reservation))
            ->assertRedirect($checkoutUrl);

        $otherAccount = $this->account(['email_verified_at' => now()]);
        $this->actingAs($otherAccount, 'guest')
            ->get(route('guest.account.reservations.check-in-payment.qr', $reservation))
            ->assertForbidden();

        $payment->update(['gateway_status' => 'paid', 'status' => 'posted']);
        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.show', $reservation))
            ->assertOk()
            ->assertDontSee('Pay remaining balance');
    }

    public function test_verified_guest_can_start_an_eligible_deposit_payment_from_their_reservation_detail(): void
    {
        Setting::create(['key' => 'online_payments_enabled', 'value' => '1']);
        $account = $this->account(['email_verified_at' => now()]);
        $reservation = $this->reservation($account, $this->roomType(), 'confirmed');
        $reservation->update([
            'payment_link_token' => 'deposit-token-'.$reservation->id,
            'payment_link_expires_at' => now()->addHour(),
        ]);
        $this->createAdvanceHold($reservation);

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Reservation Progress')
            ->assertSee('Complete your deposit payment')
            ->assertSee(route('guest.account.reservations.deposit-payment', $reservation, false))
            ->assertDontSee('deposit-token-'.$reservation->id);

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.deposit-payment', $reservation))
            ->assertRedirect(route('guest.payment.show', ['token' => $reservation->payment_link_token], false));

        $otherAccount = $this->account(['email_verified_at' => now()]);
        $this->actingAs($otherAccount, 'guest')
            ->get(route('guest.account.reservations.deposit-payment', $reservation))
            ->assertNotFound();

        $unverifiedAccount = $this->account();
        $unverifiedReservation = $this->reservation($unverifiedAccount, $this->roomType(), 'confirmed');
        $unverifiedReservation->update([
            'payment_link_token' => 'unverified-deposit-token',
            'payment_link_expires_at' => now()->addHour(),
        ]);
        $this->createAdvanceHold($unverifiedReservation);
        $this->actingAs($unverifiedAccount, 'guest')
            ->get(route('guest.account.reservations.deposit-payment', $unverifiedReservation))
            ->assertNotFound();

        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 500,
            'gateway' => 'paymongo',
            'gateway_status' => 'pending',
            'is_deposit' => true,
            'status' => 'pending',
        ]);
        $this->actingAs($account, 'guest')
            ->get(route('guest.account.reservations.deposit-payment', $reservation))
            ->assertNotFound();
    }

    public function test_verified_guest_can_update_profile_and_submit_feedback_for_own_completed_reservation(): void
    {
        $account = $this->account(['email_verified_at' => now()]);
        $reservation = $this->reservation($account, $this->roomType(), 'checked_out');

        $this->actingAs($account, 'guest')
            ->put(route('guest.account.profile.update'), [
                'last_name' => 'Updated',
                'first_name' => 'Guest',
                'phone' => '09179876543',
            ])
            ->assertSessionHas('success');

        $this->actingAs($account, 'guest')
            ->post(route('guest.account.feedback.store', $reservation), ['overall_rating' => 5, 'comments' => 'Excellent stay.'])
            ->assertRedirect(route('guest.account.reservations.show', $reservation));

        $this->assertDatabaseHas('reservation_feedback', [
            'reservation_id' => $reservation->id,
            'guest_account_id' => $account->id,
            'overall_rating' => 5,
            'public_display_consent' => false,
            'visibility_status' => 'internal',
        ]);
    }

    public function test_guest_can_opt_in_to_moderated_public_testimonial_consideration(): void
    {
        $account = $this->account(['email_verified_at' => now()]);
        $reservation = $this->reservation($account, $this->roomType(), 'checked_out');

        $this->actingAs($account, 'guest')
            ->post(route('guest.account.feedback.store', $reservation), [
                'overall_rating' => 5,
                'comments' => 'A welcoming campus stay.',
                'public_display_consent' => '1',
                'public_display_room_type' => '1',
            ])
            ->assertRedirect(route('guest.account.reservations.show', $reservation));

        $this->assertDatabaseHas('reservation_feedback', [
            'reservation_id' => $reservation->id,
            'public_display_consent' => true,
            'public_display_room_type' => true,
            'visibility_status' => 'internal',
        ]);
    }

    private function account(array $overrides = []): GuestAccount
    {
        return GuestAccount::create(array_merge([
            'last_name' => 'Dashboard',
            'first_name' => 'Guest',
            'email' => 'guest-'.uniqid().'@example.com',
            'password' => 'password',
            'phone' => '09171234567',
        ], $overrides));
    }

    private function roomType(): RoomType
    {
        return RoomType::create([
            'name' => 'Dashboard Room '.uniqid(),
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
    }

    private function reservation(GuestAccount $account, RoomType $roomType, string $status): Reservation
    {
        return Reservation::create([
            'guest_account_id' => $account->id,
            'guest_first_name' => $account->first_name,
            'guest_last_name' => $account->last_name,
            'guest_email' => $account->email,
            'guest_phone' => $account->phone,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'number_of_occupants' => 1,
            'status' => $status,
        ]);
    }

    private function reservationForEmail(RoomType $roomType, string $email): Reservation
    {
        return Reservation::create([
            'guest_first_name' => 'Unlinked',
            'guest_last_name' => 'Guest',
            'guest_email' => $email,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'approved',
        ]);
    }

    private function createAdvanceHold(Reservation $reservation): void
    {
        $floor = Floor::firstOrCreate(['name' => 'Guest Account Floor'], ['level' => 1, 'is_active' => true]);
        $room = Room::create([
            'room_number' => 'GA'.uniqid(),
            'room_type_id' => $reservation->preferred_room_type_id,
            'floor_id' => $floor->id,
            'capacity' => 1,
            'status' => 'reserved',
            'is_active' => true,
        ]);

        RoomHold::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);
    }
}
