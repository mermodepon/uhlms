<?php

namespace App\Filament\Pages\Auth;

use App\Support\AdminMfa;
use App\Support\SecurityMonitor;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;

class Login extends \Filament\Pages\Auth\Login
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $remember = (bool) ($data['remember'] ?? false);
        $guard = Filament::auth();

        if (! $guard->attempt($this->getCredentialsFromFormData($data), false)) {
            app(SecurityMonitor::class)->adminAuthentication('password_failed', request()->ip());
            $this->throwFailureValidationException();
        }

        $user = $guard->user();
        if (($user instanceof FilamentUser) && ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            $guard->logout();
            $this->throwFailureValidationException();
        }

        if ($user && AdminMfa::isEnabled($user)) {
            $pending = [
                'user_id' => $user->getAuthIdentifier(),
                'remember' => $remember,
                'expires_at' => now()->addSeconds((int) config('security.mfa.pending_ttl_seconds', 600))->timestamp,
                'intended' => AdminMfa::safeIntendedUrl(session()->pull('url.intended')),
            ];

            $guard->logout();
            session()->invalidate();
            session()->regenerateToken();
            session()->put(AdminMfa::PENDING_SESSION_KEY, $pending);

            return app(LoginResponse::class);
        }

        if ($remember) {
            $guard->login($user, true);
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
