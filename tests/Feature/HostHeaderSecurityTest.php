<?php

namespace Tests\Feature;

use App\Mail\AlternativeRoomOfferMail;
use App\Models\GuestAccount;
use App\Models\Reservation;
use App\Models\ReservationAlternativeOffer;
use App\Models\RoomType;
use App\Services\AlternativeRoomOfferService;
use App\Support\TrustedHostPatterns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class HostHeaderSecurityTest extends TestCase
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

    public function test_password_reset_email_uses_canonical_host_and_token_still_resets_password(): void
    {
        config(['app.url' => 'https://app.uhlms.uk']);
        $account = $this->guestAccount();
        $body = null;
        $this->captureRawMailBody($body);

        $this->withServerVariables(['HTTP_HOST' => 'evil.example'])
            ->post('http://evil.example'.route('guest.account.password.email', [], false), ['email' => $account->email])
            ->assertSessionHas('success');

        $url = $this->firstUrlIn($body);
        $this->assertStringStartsWith('https://app.uhlms.uk/account/reset-password/', $url);
        $this->assertStringNotContainsString('evil.example', $url);

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $token = rawurldecode(basename($parts['path']));
        $record = DB::table('guest_password_reset_tokens')->where('email', $account->email)->first();

        $this->assertNotNull($record);
        $this->assertTrue(Hash::check($token, $record->token));
        $this->assertSame($account->email, $query['email'] ?? null);
        $this->assertTrue(Carbon::parse($record->created_at)->addMinutes(60)->between(
            now()->addMinutes(59),
            now()->addMinutes(61),
        ));

        $this->post(route('guest.account.password.update'), [
            'email' => $account->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('guest.account.login'));

        $this->assertTrue(Hash::check('new-password-123', $account->fresh()->password));
    }

    public function test_password_reset_requests_are_limited_per_email(): void
    {
        Mail::fake();
        $account = $this->guestAccount();

        foreach (range(1, 3) as $attempt) {
            $this->post(route('guest.account.password.email'), ['email' => $account->email])
                ->assertSessionHas('success');
        }

        $this->post(route('guest.account.password.email'), ['email' => $account->email])
            ->assertStatus(429);
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $account = $this->guestAccount();
        $token = Str::random(64);
        DB::table('guest_password_reset_tokens')->insert([
            'email' => $account->email,
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(61),
        ]);

        $this->post(route('guest.account.password.update'), [
            'email' => $account->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password123', $account->fresh()->password));
    }

    public function test_verification_email_uses_canonical_host_and_relative_signature_remains_valid(): void
    {
        config(['app.url' => 'https://app.uhlms.uk']);
        $body = null;
        $this->captureRawMailBody($body);

        $this->withServerVariables(['HTTP_HOST' => 'evil.example'])
            ->post('http://evil.example'.route('guest.account.register.submit', [], false), [
                'last_name' => 'Security',
                'first_name' => 'Guest',
                'email' => 'security-guest@example.test',
                'phone' => '09171234567',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('guest.account.dashboard'));

        $url = $this->firstUrlIn($body);
        $this->assertStringStartsWith('https://app.uhlms.uk/account/verify/', $url);
        $this->assertStringNotContainsString('evil.example', $url);

        $verificationRequest = Request::create($url);
        $this->assertTrue(URL::hasValidSignature($verificationRequest, false));
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, (int) ($query['expires'] ?? 0), 5);

        $relativeUrl = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->get($relativeUrl)->assertRedirect(route('guest.account.dashboard'));
        $this->assertNotNull(GuestAccount::where('email', 'security-guest@example.test')->firstOrFail()->email_verified_at);
    }

    public function test_alternative_offer_email_uses_lan_canonical_host_and_relative_signature_remains_valid(): void
    {
        config(['app.url' => 'http://192.168.1.235:8000']);
        Mail::fake();

        $roomType = $this->roomType();
        $reservation = $this->reservation($roomType);
        $offer = ReservationAlternativeOffer::create([
            'reservation_id' => $reservation->id,
            'offered_room_type_id' => $roomType->id,
            'room_ids' => [],
            'original_total' => 1000,
            'quoted_total' => 900,
            'status' => ReservationAlternativeOffer::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        app(AlternativeRoomOfferService::class)->sendOfferEmail($offer);

        $offerUrl = null;
        Mail::assertSent(AlternativeRoomOfferMail::class, function (AlternativeRoomOfferMail $mail) use (&$offerUrl): bool {
            $offerUrl = $mail->offerUrl;

            return true;
        });

        $this->assertIsString($offerUrl);
        $this->assertStringStartsWith('http://192.168.1.235:8000/alternative-offers/', $offerUrl);
        $this->assertStringNotContainsString('evil.example', $offerUrl);
        $this->assertTrue(URL::hasValidSignature(Request::create($offerUrl), false));
        parse_str(parse_url($offerUrl, PHP_URL_QUERY) ?: '', $query);
        $this->assertSame($offer->expires_at->timestamp, (int) ($query['expires'] ?? 0));
    }

    public function test_trusted_host_patterns_accept_only_exact_configured_hosts(): void
    {
        $hosts = ['app.uhlms.uk', 'localhost', '127.0.0.1', '::1', '192.168.1.235'];
        $patterns = TrustedHostPatterns::fromLiterals($hosts);

        $this->assertSame([
            '^app\\.uhlms\\.uk$',
            '^localhost$',
            '^127\\.0\\.0\\.1$',
            '^\\[\:\:1\\]$',
            '^192\\.168\\.1\\.235$',
        ], $patterns);

        Request::setTrustedHosts($patterns);

        try {
            foreach ($hosts as $host) {
                $header = $host === '::1' ? '[::1]:8000' : $host.':8000';
                $expectedHost = $host === '::1' ? '[::1]' : $host;
                $this->assertSame($expectedHost, Request::create('/', server: ['HTTP_HOST' => $header])->getHost());
            }

            foreach (['evil.example', 'sub.app.uhlms.uk', '192.168.1.236'] as $host) {
                $rejected = false;

                try {
                    Request::create('/', server: ['HTTP_HOST' => $host])->getHost();
                } catch (SuspiciousOperationException) {
                    $rejected = true;
                }

                $this->assertTrue($rejected, "Host {$host} should have been rejected.");
            }
        } finally {
            Request::setTrustedHosts([]);
        }
    }

    public function test_production_middleware_rejects_unknown_and_forwarded_hosts_before_email_is_sent(): void
    {
        $this->app->instance('env', 'production');
        $account = $this->guestAccount();
        Mail::shouldReceive('raw')->never();

        $this->withServerVariables(['HTTP_HOST' => 'evil.example'])
            ->post('http://evil.example'.route('guest.account.password.email', [], false), ['email' => $account->email])
            ->assertBadRequest();

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'app.uhlms.uk',
            'HTTP_X_FORWARDED_HOST' => 'evil.example',
        ])->get('/up')->assertBadRequest();

        $this->assertDatabaseMissing('guest_password_reset_tokens', ['email' => $account->email]);
    }

    public function test_production_middleware_accepts_each_configured_runtime_host(): void
    {
        $this->app->instance('env', 'production');

        foreach (['app.uhlms.uk', 'localhost', '127.0.0.1', '192.168.1.235'] as $host) {
            $this->withServerVariables(['HTTP_HOST' => $host])->get('/up')->assertOk();
        }
    }

    private function captureRawMailBody(?string &$body): void
    {
        Mail::shouldReceive('raw')
            ->once()
            ->withArgs(function (string $text) use (&$body): bool {
                $body = $text;

                return true;
            })
            ->andReturnNull();
    }

    private function firstUrlIn(?string $body): string
    {
        $this->assertIsString($body);
        $this->assertMatchesRegularExpression('~https?://[^\s]+~', $body);
        preg_match('~https?://[^\s]+~', $body, $matches);

        return $matches[0];
    }

    private function guestAccount(): GuestAccount
    {
        return GuestAccount::create([
            'last_name' => 'Account',
            'first_name' => 'Security',
            'email' => 'security-'.uniqid().'@example.test',
            'password' => 'password123',
        ]);
    }

    private function roomType(): RoomType
    {
        return RoomType::create([
            'name' => 'Security '.uniqid(),
            'base_rate' => 1000,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
    }

    private function reservation(RoomType $roomType): Reservation
    {
        return Reservation::create([
            'guest_first_name' => 'Offer',
            'guest_last_name' => 'Guest',
            'guest_email' => 'offer-'.uniqid().'@example.test',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'awaiting_alternative_confirmation',
        ]);
    }
}
