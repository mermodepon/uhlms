<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminMfa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminMfaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fortify_does_not_register_competing_authentication_routes(): void
    {
        $fortifyRoutes = collect(Route::getRoutes())->filter(
            fn ($route): bool => str_starts_with((string) $route->getName(), 'two-factor.')
                || str_starts_with((string) $route->uri(), 'admin/security'),
        );

        $this->assertCount(0, $fortifyRoutes);
    }

    public function test_enforced_admin_is_limited_to_setup_until_mfa_is_confirmed(): void
    {
        config()->set('security.mfa.mode', 'enforce');
        $user = $this->user('admin');

        $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.mfa.setup'));
        $this->actingAs($user)->get(route('admin.mfa.setup'))->assertOk();
    }

    public function test_optional_staff_can_enroll_and_is_challenged_after_enrollment(): void
    {
        config()->set('security.mfa.mode', 'optional');
        $staff = $this->user('staff');
        $this->actingAs($staff)->get('/admin')->assertOk();

        [$staff, $secret] = $this->enableMfa($staff);
        $this->assertTrue(AdminMfa::verify($staff, (new Google2FA)->getCurrentOtp($secret), null));
        auth()->logout();
        $pending = [
            'user_id' => $staff->id,
            'remember' => false,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'intended' => '/admin',
        ];
        $this->withSession([AdminMfa::PENDING_SESSION_KEY => $pending])
            ->post(route('admin.mfa.challenge.verify'), ['recovery_code' => 'recovery-code'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($staff);
        $this->assertNotContains('recovery-code', $staff->fresh()->recoveryCodes());
        $this->assertCount(1, $staff->fresh()->recoveryCodes());
    }

    public function test_expired_pending_login_cannot_complete_authentication(): void
    {
        [$user] = $this->enableMfa($this->user('admin'));

        $this->withSession([AdminMfa::PENDING_SESSION_KEY => [
            'user_id' => $user->id,
            'expires_at' => now()->subSecond()->timestamp,
        ]])->post(route('admin.mfa.challenge.verify'), ['recovery_code' => 'recovery-code'])
            ->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ])->refresh();
    }

    /** @return array{User, string} */
    private function enableMfa(User $user): array
    {
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }
}
