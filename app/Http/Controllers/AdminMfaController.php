<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AdminMfa;
use App\Support\SecurityAudit;
use App\Support\SecurityMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class AdminMfaController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/admin');
        }

        if (! $this->pendingUser($request)) {
            return redirect('/admin/login')->withErrors(['email' => 'Your MFA challenge expired. Please log in again.']);
        }

        return view('filament.auth.mfa-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect('/admin/login')->withErrors(['email' => 'Your MFA challenge expired. Please log in again.']);
        }

        $data = $this->validateVerificationInput($request);
        $key = 'admin-mfa-challenge:'.$user->id.':'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! AdminMfa::verify($user, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            RateLimiter::hit($key, 60);
            app(SecurityMonitor::class)->adminAuthentication('mfa_challenge_failed', $request->ip());

            return back()->withErrors(['code' => 'The authentication code was invalid.']);
        }

        RateLimiter::clear($key);
        $pending = $request->session()->pull(AdminMfa::PENDING_SESSION_KEY);
        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();
        AdminMfa::markRecent();

        return redirect()->to(AdminMfa::safeIntendedUrl($pending['intended'] ?? null));
    }

    public function setup(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('filament.auth.mfa-setup', [
            'user' => $user,
            'enabled' => AdminMfa::isEnabled($user),
            'pendingConfirmation' => filled($user->two_factor_secret) && ! $user->two_factor_confirmed_at,
            'qrCode' => filled($user->two_factor_secret) && ! $user->two_factor_confirmed_at
                ? $user->twoFactorQrCodeSvg()
                : null,
        ]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable, SecurityAudit $audit): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $audit->record('mfa_enrollment_started', ['actor_id' => $request->user()->id]);
        $enable($request->user(), true);

        return redirect()->route('admin.mfa.setup')->with('status', 'Scan the QR code and enter a code to finish setup.');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm, SecurityAudit $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $audit->record('mfa_enrollment_confirmation_requested', ['actor_id' => $request->user()->id]);
        $confirm($request->user(), $data['code']);
        AdminMfa::markRecent();
        $audit->record('mfa_enrollment_confirmed', ['actor_id' => $request->user()->id]);

        return redirect()->route('admin.mfa.setup')
            ->with('status', 'Multi-factor authentication is now enabled.')
            ->with('mfa_recovery_codes', $request->user()->fresh()->recoveryCodes());
    }

    public function regenerate(Request $request, GenerateNewRecoveryCodes $generate, SecurityAudit $audit): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        AdminMfa::requireRecent();
        $audit->record('mfa_recovery_codes_regeneration_requested', ['actor_id' => $request->user()->id]);
        $generate($request->user());

        return redirect()->route('admin.mfa.setup')
            ->with('status', 'New recovery codes were generated. Previous codes no longer work.')
            ->with('mfa_recovery_codes', $request->user()->fresh()->recoveryCodes());
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable, SecurityAudit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if(AdminMfa::isMandatory($user), 403, 'MFA cannot be disabled for this role.');
        $request->validate(['password' => ['required', 'current_password']]);
        AdminMfa::requireRecent();
        $audit->record('mfa_self_disable_requested', ['actor_id' => $user->id]);
        $disable($user);
        $request->session()->forget(AdminMfa::RECENT_SESSION_KEY);

        return redirect()->route('admin.mfa.setup')->with('status', 'Multi-factor authentication was disabled.');
    }

    public function confirmRecent(Request $request): View|RedirectResponse
    {
        if (! AdminMfa::isEnabled($request->user())) {
            return redirect()->route('admin.mfa.setup');
        }

        $request->session()->put('admin_mfa_recent_intended', AdminMfa::safeIntendedUrl(
            $request->query('intended', url()->previous()),
        ));

        return view('filament.auth.mfa-confirm');
    }

    public function verifyRecent(Request $request): RedirectResponse
    {
        $data = $this->validateVerificationInput($request);
        $key = 'admin-mfa-recent:'.$request->user()->id.':'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! AdminMfa::verify($request->user(), $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            RateLimiter::hit($key, 60);
            app(SecurityMonitor::class)->adminAuthentication('mfa_recent_failed', $request->ip());

            return back()->withErrors(['code' => 'The authentication code was invalid.']);
        }

        RateLimiter::clear($key);
        AdminMfa::markRecent();

        return redirect()->to(AdminMfa::safeIntendedUrl(
            $request->session()->pull('admin_mfa_recent_intended'),
        ));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(AdminMfa::PENDING_SESSION_KEY);
        if (! is_array($pending) || (int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(AdminMfa::PENDING_SESSION_KEY);

            return null;
        }

        $user = User::find($pending['user_id'] ?? null);

        return $user && AdminMfa::isEnabled($user) ? $user : null;
    }

    private function validateVerificationInput(Request $request): array
    {
        return $request->validate([
            'code' => ['nullable', 'digits:6', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:100', 'required_without:code'],
        ]);
    }
}
