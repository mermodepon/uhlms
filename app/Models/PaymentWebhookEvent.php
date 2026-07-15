<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'gateway',
        'event_id',
        'event_type',
        'livemode',
        'payload_sha256',
        'signature_timestamp',
        'status',
        'attempts',
        'processed_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'signature_timestamp' => 'integer',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
