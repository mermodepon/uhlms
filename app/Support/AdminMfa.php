<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class AdminMfa
{
    public const RECENT_SESSION_KEY = 'auth.mfa_verified_at';

    public const PENDING_SESSION_KEY = 'admin_mfa_pending';

    public static function isMandatory(User $user): bool
    {
        return in_array($user->role, config('security.mfa.required_roles', []), true);
    }

    public static function isEnforced(): bool
    {
        return config('security.mfa.mode', 'optional') === 'enforce';
    }

    public static function isEnabled(User $user): bool
    {
        return $user->hasEnabledTwoFactorAuthentication();
    }

    public static function markRecent(): void
    {
        session()->put(self::RECENT_SESSION_KEY, now()->timestamp);
    }

    public static function isRecent(): bool
    {
        $verifiedAt = (int) session(self::RECENT_SESSION_KEY, 0);
        $lifetime = max(60, (int) config('security.mfa.recent_seconds', 600));

        return $verifiedAt > 0 && $verifiedAt >= now()->subSeconds($lifetime)->timestamp;
    }

    public static function requireRecent(): void
    {
        abort_unless(self::isRecent(), 403, 'Recent multi-factor authentication is required.');
    }

    public static function verify(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if (! self::isEnabled($user)) {
            return false;
        }

        if (filled($code)) {
            try {
                $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

                return app(TwoFactorAuthenticationProvider::class)->verify($secret, trim((string) $code));
            } catch (\Throwable) {
                return false;
            }
        }

        if (! filled($recoveryCode)) {
            return false;
        }

        return DB::transaction(function () use ($user, $recoveryCode): bool {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);
            if (! $lockedUser || ! self::isEnabled($lockedUser)) {
                return false;
            }

            try {
                foreach ($lockedUser->recoveryCodes() as $storedCode) {
                    if (hash_equals((string) $storedCode, trim((string) $recoveryCode))) {
                        $lockedUser->replaceRecoveryCode($storedCode);

                        return true;
                    }
                }
            } catch (\Throwable) {
                return false;
            }

            return false;
        });
    }

    public static function safeIntendedUrl(?string $url, string $fallback = '/admin'): string
    {
        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $fallback;
        }

        return $url;
    }
}
