<?php

namespace App\Support;

use LogicException;

class ProductionConfigurationValidator
{
    public static function validate(bool $isProduction): void
    {
        if (! $isProduction) {
            return;
        }

        $unsafeSettings = [
            'APP_DEBUG must be false.' => config('app.debug') === true,
            'SESSION_SECURE_COOKIE must be true.' => config('session.secure') !== true,
            'PAYMONGO_STRICT_WEBHOOK_VERIFICATION must be true.' => config('paymongo.strict_webhook_verification') !== true,
            'PAYMONGO_ALLOW_INSECURE_TLS must be false.' => config('paymongo.allow_insecure_tls') === true,
        ];

        foreach ($unsafeSettings as $message => $unsafe) {
            if ($unsafe) {
                throw new LogicException($message);
            }
        }
    }
}