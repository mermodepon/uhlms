<?php

namespace Tests\Unit\Support;

use App\Support\ProductionConfigurationValidator;
use LogicException;
use Tests\TestCase;

class ProductionConfigurationValidatorTest extends TestCase
{
    public function test_it_allows_safe_production_configuration(): void
    {
        config()->set([
            'app.debug' => false,
            'session.secure' => true,
            'paymongo.strict_webhook_verification' => true,
            'paymongo.allow_insecure_tls' => false,
        ]);

        ProductionConfigurationValidator::validate(true);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @dataProvider unsafeProductionSettings
     */
    public function test_it_rejects_each_unsafe_production_setting(string $key, mixed $value, string $message): void
    {
        config()->set([
            'app.debug' => false,
            'session.secure' => true,
            'paymongo.strict_webhook_verification' => true,
            'paymongo.allow_insecure_tls' => false,
            $key => $value,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        ProductionConfigurationValidator::validate(true);
    }

    public static function unsafeProductionSettings(): array
    {
        return [
            'debug enabled' => ['app.debug', true, 'APP_DEBUG must be false.'],
            'insecure session cookies' => ['session.secure', false, 'SESSION_SECURE_COOKIE must be true.'],
            'webhook validation disabled' => ['paymongo.strict_webhook_verification', false, 'PAYMONGO_STRICT_WEBHOOK_VERIFICATION must be true.'],
            'insecure payment TLS enabled' => ['paymongo.allow_insecure_tls', true, 'PAYMONGO_ALLOW_INSECURE_TLS must be false.'],
        ];
    }
}