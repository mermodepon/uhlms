<?php

namespace App\Console\Commands;

use App\Support\PayMongoPaymentMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SanitizePaymentGatewayMetadata extends Command
{
    protected $signature = 'payments:sanitize-gateway-metadata
                            {--dry-run : Report the records that would change without writing}
                            {--force : Apply the metadata cleanup}';

    protected $description = 'Remove raw PayMongo payloads and terminal checkout URLs from payment metadata';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun === $force) {
            $this->error('Choose exactly one of --dry-run or --force.');

            return self::INVALID;
        }

        $counts = [
            'scanned' => 0,
            'changed' => 0,
            'raw_payload_records' => 0,
            'terminal_checkout_urls' => 0,
        ];

        try {
            DB::transaction(function () use (&$counts, $force): void {
                DB::table('reservation_payments')
                    ->select(['id', 'gateway_status', 'gateway_metadata'])
                    ->whereNotNull('gateway_metadata')
                    ->orderBy('id')
                    ->chunkById(200, function ($payments) use (&$counts, $force): void {
                        foreach ($payments as $payment) {
                            $counts['scanned']++;
                            $metadata = $this->decodeMetadata($payment->gateway_metadata);
                            $hasRawPayload = array_key_exists('payment_data', $metadata)
                                || array_key_exists('source_data', $metadata);
                            $hasTerminalCheckoutUrl = ($payment->gateway_status ?? null) !== 'pending'
                                && array_key_exists('checkout_url', $metadata);
                            $sanitized = PayMongoPaymentMetadata::sanitize(
                                $metadata,
                                is_string($payment->gateway_status) ? $payment->gateway_status : null,
                            );

                            if ($hasRawPayload) {
                                $counts['raw_payload_records']++;
                            }

                            if ($hasTerminalCheckoutUrl) {
                                $counts['terminal_checkout_urls']++;
                            }

                            if ($sanitized === $metadata) {
                                continue;
                            }

                            $counts['changed']++;

                            if ($force) {
                                DB::table('reservation_payments')
                                    ->where('id', $payment->id)
                                    ->update([
                                        'gateway_metadata' => json_encode(
                                            $sanitized,
                                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                                        ),
                                    ]);
                            }
                        }
                    });
            });
        } catch (\Throwable $exception) {
            $this->error('Gateway metadata cleanup failed; no changes were committed.');

            return self::FAILURE;
        }

        $this->table(
            ['Mode', 'Scanned', 'Changed', 'Raw payload records', 'Terminal checkout URLs'],
            [[
                $dryRun ? 'dry-run' : 'applied',
                $counts['scanned'],
                $counts['changed'],
                $counts['raw_payload_records'],
                $counts['terminal_checkout_urls'],
            ]],
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Payment gateway metadata is not a JSON object.');
        }

        return $decoded;
    }
}
