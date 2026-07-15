<?php

namespace Tests\Feature;

use App\Mail\SendPaymentLinkMail;
use App\Models\GuestAccount;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReservationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    private function createRoom(RoomType $roomType): Room
    {
        $floor = \App\Models\Floor::firstOrCreate(
            ['name' => 'Payment Test Floor'],
            ['level' => 1, 'is_active' => true]
        );

        return Room::create([
            'room_number' => 'PAY'.uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => 'available',
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

    private function addAdvanceHold(Reservation $reservation): RoomHold
    {
        $room = $this->createRoom($reservation->preferredRoomType);

        return RoomHold::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);
    }

    private function issuePaymentLink(Reservation $reservation): Reservation
    {
        $reservation->issueGuestPaymentLink(rotateToken: true);
        $reservation->save();

        return $reservation->fresh();
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

    public function test_approved_reservation_without_room_hold_payment_page_is_not_available(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);

        $response = $this->get(route('guest.payment.show', ['token' => $reservation->payment_link_token]));

        $response->assertNotFound();
    }

    public function test_approved_reservation_with_room_hold_payment_page_is_available(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);

        $response = $this->get(route('guest.payment.show', ['token' => $reservation->payment_link_token]));

        $response->assertOk();
        $response->assertSee('Complete Your Payment');
    }

    public function test_valid_payment_token_displays_success_details_with_secure_tracking_link(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);

        $response = $this->get(route('guest.payment.success', [
            'token' => $reservation->payment_link_token,
        ]));

        $response->assertOk();
        $response->assertSee($reservation->reference_number);
        $response->assertSee($reservation->guest_name);
        $response->assertSee('Track Reservation');
        $response->assertSee('Return to Homepage');
        $response->assertDontSee('Return to Reservation');
        $response->assertSee('signature=', false);
        $response->assertDontSee('guest_email=', false);
        $response->assertDontSee(urlencode($reservation->guest_email), false);
    }

    public function test_initial_payment_results_return_a_linked_guest_to_their_reservation(): void
    {
        $account = GuestAccount::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'password' => 'password',
            'phone' => '09171234567',
        ]);
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update([
            'approved_at' => now(),
            'guest_account_id' => $account->id,
            'guest_email' => $account->email,
        ]);
        $this->addAdvanceHold($reservation);

        foreach (['guest.payment.success', 'guest.payment.failed'] as $routeName) {
            $this->actingAs($account, 'guest')
                ->get(route($routeName, ['token' => $reservation->payment_link_token]))
                ->assertOk()
                ->assertSee('Return to Reservation')
                ->assertSee(route('guest.account.reservations.show', $reservation, false), false)
                ->assertDontSee('Return to Homepage');
        }
    }

    public function test_valid_payment_token_displays_failure_details_and_retry_link(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);

        $response = $this->get(route('guest.payment.failed', [
            'token' => $reservation->payment_link_token,
        ]));

        $response->assertOk();
        $response->assertSee($reservation->reference_number);
        $response->assertSee($reservation->guest_name);
        $response->assertSee('Try Again');
        $response->assertSee('Return to Homepage');
        $response->assertDontSee('Return to Reservation');
        $response->assertSee($reservation->generatePaymentLink(false), false);
    }

    public function test_payment_failure_uses_saved_support_contact_information(): void
    {
        Setting::set('guest_footer_email', 'saved-support@example.test');
        Setting::set('guest_footer_phone', '+63 917 000 1234');

        $response = $this->get(route('guest.payment.failed'));

        $response->assertOk();
        $response->assertSee('mailto:saved-support@example.test', false);
        $response->assertSee('tel:+639170001234', false);
        $response->assertDontSee('support@uhlms.edu.ph');
        $response->assertDontSee('(123) 456-7890');
    }

    public function test_payment_failure_hides_contact_block_when_no_contact_is_saved(): void
    {
        Setting::set('guest_footer_email', '');
        Setting::set('guest_footer_phone', '');

        $response = $this->get(route('guest.payment.failed'));

        $response->assertOk();
        $response->assertDontSee('Need help? Contact us at:');
        $response->assertDontSee('support@uhlms.edu.ph');
        $response->assertDontSee('(123) 456-7890');
    }

    public function test_expired_payment_page_uses_saved_contacts_and_signed_tracking_url(): void
    {
        Setting::set('guest_footer_email', 'saved-support@example.test');
        Setting::set('guest_footer_phone', '+63 917 000 1234');

        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update([
            'approved_at' => now(),
            'payment_link_expires_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('guest.payment.show', [
            'token' => $reservation->payment_link_token,
        ]));

        $response->assertOk();
        $response->assertSee('Payment Link Has Expired');
        $response->assertSee('mailto:saved-support@example.test', false);
        $response->assertSee('tel:+639170001234', false);
        $response->assertSee('signature=', false);
        $response->assertDontSee('guest_email=', false);
        $response->assertDontSee(urlencode($reservation->guest_email), false);
        $response->assertDontSee('support@uhlms.edu.ph');
        $response->assertDontSee('(123) 456-7890');
    }

    public function test_payment_link_email_uses_saved_support_contact_information(): void
    {
        Setting::set('guest_footer_email', 'saved-support@example.test');
        Setting::set('guest_footer_phone', '+63 917 000 1234');

        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $html = (new SendPaymentLinkMail($reservation))->render();

        $this->assertStringContainsString('mailto:saved-support@example.test', $html);
        $this->assertStringContainsString('tel:+639170001234', $html);
        $this->assertStringNotContainsString('support@uhlms.edu.ph', $html);
        $this->assertStringNotContainsString('(123) 456-7890', $html);
    }

    public function test_payment_result_pages_do_not_disclose_details_without_a_valid_token(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);

        $requests = [
            route('guest.payment.success'),
            route('guest.payment.success', ['reservation' => $reservation->reference_number]),
            route('guest.payment.success', ['token' => (string) Str::uuid()]),
            route('guest.payment.failed'),
            route('guest.payment.failed', ['reservation' => $reservation->reference_number]),
            route('guest.payment.failed', ['token' => (string) Str::uuid()]),
        ];

        foreach ($requests as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('Reservation details are protected');
            $response->assertDontSee($reservation->reference_number);
            $response->assertDontSee($reservation->guest_name);
            $response->assertDontSee($reservation->guest_email);
            $response->assertDontSee('Try Again');
        }
    }

    public function test_expired_payment_token_displays_only_generic_guidance(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update([
            'approved_at' => now(),
            'payment_link_expires_at' => now()->subMinute(),
        ]);

        foreach (['guest.payment.success', 'guest.payment.failed'] as $routeName) {
            $response = $this->get(route($routeName, [
                'token' => $reservation->payment_link_token,
            ]));

            $response->assertOk();
            $response->assertSee('Reservation details are protected');
            $response->assertDontSee($reservation->reference_number);
            $response->assertDontSee($reservation->guest_name);
        }
    }

    public function test_checkout_session_uses_token_based_result_urls(): void
    {
        config(['app.url' => 'https://app.uhlms.uk']);
        $this->withServerVariables([
            'HTTP_HOST' => 'localhost:8000',
        ]);
        config(['paymongo.secret_key' => 'sk_test_example']);
        Http::fake([
            '*/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_guest_payment',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/cs_guest_payment',
                        'payment_intent' => ['id' => 'pi_guest_payment'],
                        'payment_method_types' => ['gcash'],
                    ],
                ],
            ]),
        ]);

        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);

        $response = $this->post(route('guest.payment.initialize', [
            'token' => $reservation->payment_link_token,
        ]), [
            'accept_terms' => '1',
            'payment_method' => 'gcash',
            'payment_type' => 'deposit',
        ]);

        $response->assertRedirect('https://checkout.paymongo.com/cs_guest_payment');

        Http::assertSent(function ($request) use ($reservation): bool {
            $successUrl = (string) data_get($request->data(), 'data.attributes.success_url');
            $cancelUrl = (string) data_get($request->data(), 'data.attributes.cancel_url');

            return str_contains($successUrl, 'token='.urlencode($reservation->payment_link_token))
                && str_contains($cancelUrl, 'token='.urlencode($reservation->payment_link_token))
                && str_starts_with($successUrl, 'https://app.uhlms.uk/')
                && ! str_contains($successUrl, 'reservation=')
                && ! str_contains($cancelUrl, 'reservation=');
        });
    }

    public function test_already_paid_redirect_uses_payment_token(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 1000,
            'payment_mode' => 'GCash',
            'gateway' => 'paymongo',
            'gateway_payment_id' => 'pay_existing_deposit',
            'gateway_status' => 'paid',
            'is_deposit' => true,
            'status' => 'posted',
        ]);

        $this->get(route('guest.payment.show', [
            'token' => $reservation->payment_link_token,
        ]))->assertRedirect(route('guest.payment.success', [
            'token' => $reservation->payment_link_token,
        ]));
    }

    public function test_tracking_page_uses_local_payment_qr_code(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertOk();
        $response->assertSee(route('guest.payment.qr', ['token' => $reservation->payment_link_token], false), false);
        $response->assertDontSee('api.qrserver.com');
    }

    public function test_tracking_page_uses_the_same_estimated_deposit_balance_as_the_account_page(): void
    {
        $reservation = $this->createReservation('confirmed');
        $reservation->preferredRoomType->update(['base_rate' => 800]);
        $reservation->update([
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 160,
            'payment_mode' => 'GCash',
            'gateway' => 'paymongo',
            'gateway_status' => 'paid',
            'is_deposit' => true,
            'status' => 'posted',
        ]);

        $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]))
            ->assertOk()
            ->assertSee('Estimated Total:')
            ->assertSee('₱800.00')
            ->assertSee('Estimated Remaining Balance:')
            ->assertSee('₱640.00');
    }

    public function test_checkin_balance_payment_result_never_returns_a_guest_to_the_staff_checkin_page(): void
    {
        $reservation = $this->createReservation('confirmed');
        $token = (string) Str::uuid();
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 640,
            'payment_mode' => 'PayMongo Online',
            'gateway' => 'paymongo',
            'gateway_status' => 'paid',
            'is_deposit' => false,
            'status' => 'posted',
            'gateway_metadata' => ['guest_result_token' => $token],
            'meta' => [
                'source' => 'checkin_balance',
                'payment_type' => 'checkin_balance',
                'guest_result_token' => $token,
            ],
        ]);

        $this->get(route('guest.check-in-payment.result', ['token' => $token]))
            ->assertOk()
            ->assertSee('Payment received')
            ->assertSee('PHP 640.00')
            ->assertSee($reservation->reference_number)
            ->assertSee('Return to homepage')
            ->assertDontSee('Return to reservation')
            ->assertDontSee('Check In Guest');

        $this->get(route('guest.check-in-payment.result', ['token' => (string) Str::uuid()]))
            ->assertNotFound();
    }

    public function test_checkin_balance_payment_result_returns_linked_guest_to_their_reservation(): void
    {
        $account = GuestAccount::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'password' => 'password',
            'phone' => '09171234567',
        ]);
        $reservation = $this->createReservation('confirmed');
        $reservation->update([
            'guest_account_id' => $account->id,
            'guest_email' => $account->email,
        ]);
        $token = (string) Str::uuid();

        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 640,
            'payment_mode' => 'PayMongo Online',
            'gateway' => 'paymongo',
            'gateway_status' => 'paid',
            'is_deposit' => false,
            'status' => 'posted',
            'gateway_metadata' => ['guest_result_token' => $token],
            'meta' => [
                'source' => 'checkin_balance',
                'payment_type' => 'checkin_balance',
                'guest_result_token' => $token,
            ],
        ]);

        $this->actingAs($account, 'guest')
            ->get(route('guest.check-in-payment.result', ['token' => $token]))
            ->assertOk()
            ->assertSee('Return to reservation')
            ->assertSee(route('guest.account.reservations.show', $reservation, false), false)
            ->assertDontSee('Return to homepage');
    }

    public function test_checkin_result_token_survives_gateway_metadata_sanitization(): void
    {
        $reservation = $this->createReservation('confirmed');
        $token = (string) Str::uuid();
        $payment = ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 640,
            'payment_mode' => 'PayMongo Online',
            'gateway' => 'paymongo',
            'gateway_status' => 'pending',
            'is_deposit' => false,
            'status' => 'pending',
            'gateway_metadata' => ['guest_result_token' => $token],
            'meta' => [
                'source' => 'checkin_balance',
                'payment_type' => 'checkin_balance',
                'guest_result_token' => $token,
            ],
        ]);

        $payment->update([
            'gateway_status' => 'paid',
            'status' => 'posted',
            'gateway_metadata' => [],
        ]);

        $this->get(route('guest.check-in-payment.result', ['token' => $token]))
            ->assertOk()
            ->assertSee('Payment received')
            ->assertSee('PHP 640.00');
    }

    public function test_local_payment_qr_code_renders_svg_for_valid_payment_link(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $reservation->update(['approved_at' => now()]);
        $this->addAdvanceHold($reservation);

        $response = $this->get(route('guest.payment.qr', ['token' => $reservation->payment_link_token]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $response->assertSee('<svg', false);
    }

    public function test_local_payment_qr_code_rejects_expired_payment_link(): void
    {
        $reservation = $this->issuePaymentLink($this->createReservation('approved'));
        $this->addAdvanceHold($reservation);
        $reservation->update([
            'approved_at' => now(),
            'payment_link_expires_at' => now()->subMinute(),
        ]);

        $this->get(route('guest.payment.qr', ['token' => $reservation->payment_link_token]))
            ->assertNotFound();
    }

    public function test_approval_without_rooms_requires_staff_to_select_rooms_or_send_an_alternative_offer(): void
    {
        $reservation = $this->createReservation('pending');
        $staleToken = (string) Str::uuid();

        $reservation->update([
            'payment_link_token' => $staleToken,
            'payment_link_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($this->createStaffUser());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Select exactly the requested number of rooms before approving, or send the guest an alternative room offer.');

        app(ReservationWorkflowService::class)->approve($reservation);
    }

    public function test_approval_with_room_hold_rotates_stale_payment_token_before_guest_payment_opens(): void
    {
        $reservation = $this->createReservation('pending');
        $room = $this->createRoom($reservation->preferredRoomType);
        $staleToken = (string) Str::uuid();

        $reservation->update([
            'payment_link_token' => $staleToken,
            'payment_link_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($this->createStaffUser());
        app(ReservationWorkflowService::class)->approve($reservation, [
            'assigned_room_ids' => [$room->id],
        ]);

        $fresh = $reservation->fresh();

        $this->assertSame('confirmed', $fresh->status);
        $this->assertNotSame($staleToken, $fresh->payment_link_token);
        $this->get(route('guest.payment.show', ['token' => $staleToken]))->assertNotFound();
        $this->get(route('guest.payment.show', ['token' => $fresh->payment_link_token]))->assertOk();
    }
}
