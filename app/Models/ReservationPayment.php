<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPayment extends Model
{
    protected $fillable = [
        'reservation_id',
        'amount',
        'payment_mode',
        'gateway',
        'gateway_payment_id',
        'gateway_source_id',
        'gateway_status',
        'gateway_metadata',
        'is_deposit',
        'reference_no',
        'or_date',
        'status',
        'received_by',
        'received_at',
        'remarks',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'meta' => 'array',
            'gateway_metadata' => 'array',
            'is_deposit' => 'boolean',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
