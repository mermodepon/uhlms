<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\GuestAccount;
use App\Services\ReservationAccountLinker;
use App\Support\CanonicalAppUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('guest.account.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|email|max:255|unique:guest_accounts,email',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^(09\d{9}|\+639\d{9}|639\d{9})$/'],
            'gender' => 'nullable|in:Male,Female,Other',
            'age' => 'nullable|integer|min:18|max:120',
            'address' => 'nullable|string|max:1000',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $account = GuestAccount::create($data);
        Auth::guard('guest')->login($account);
        $sent = $this->sendVerificationLink($account);

        return redirect()->route('guest.account.dashboard')
            ->with('success', $sent
                ? 'Account created. Please check your email to verify your account.'
                : 'Account created, but the verification email could not be sent right now. You can resend it from your dashboard.'
            );
    }

    public function showLogin()
    {
        return view('guest.account.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('guest')->attempt($credentials, $remember)) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $account = Auth::guard('guest')->user();

        if ($account->isDisabled()) {
            Auth::guard('guest')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This guest account has been disabled.'])->onlyInput('email');
        }

        $account->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('guest.account.dashboard', [], false));
    }

    public function logout(Request $request)
    {
        Auth::guard('guest')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('guest.home');
    }

    public function verify(Request $request, GuestAccount $account)
    {
        abort_unless($request->hasValidSignature(false), 403);

        if (! $account->hasVerifiedEmail()) {
            $account->forceFill(['email_verified_at' => now()])->save();
        }

        $linkedCount = app(ReservationAccountLinker::class)->linkUnclaimedReservations($account);

        if (! Auth::guard('guest')->check()) {
            Auth::guard('guest')->login($account);
        }

        return redirect()->route('guest.account.dashboard')
            ->with('success', $linkedCount > 0
                ? "Email verified. {$linkedCount} matching reservation(s) were linked to your account."
                : 'Email verified. Matching reservations will appear here automatically.');
    }

    public function resendVerification(Request $request)
    {
        $account = Auth::guard('guest')->user();
        $sent = false;

        if ($account && ! $account->hasVerifiedEmail()) {
            $sent = $this->sendVerificationLink($account);
        }

        return back()->with('success', $sent
            ? 'Verification email sent.'
            : 'Verification email could not be sent right now. Please try again later.'
        );
    }

    public static function sendVerificationLinkFor(GuestAccount $account): bool
    {
        return (new self())->sendVerificationLink($account);
    }

    private function sendVerificationLink(GuestAccount $account): bool
    {
        $relative = URL::temporarySignedRoute(
            'guest.account.verify',
            now()->addHours(24),
            ['account' => $account->id],
            false
        );
        $url = CanonicalAppUrl::fromRelative($relative);

        try {
            Mail::raw(
                "Hello {$account->name},\n\nVerify your University Homestay guest account using this link:\n{$url}\n\nThis link expires in 24 hours.",
                fn ($message) => $message->to($account->email)->subject('Verify your guest account')
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
