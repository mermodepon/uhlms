<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckInSnapshot extends Model
{
    protected $fillable = [
        'reservation_id',
        'guest_id',
        'id_type',
        'id_number',
        'nationality',
        'purpose_of_stay',
        'detailed_checkin_datetime',
        'detailed_checkout_datetime',
        'payment_mode',
        'payment_amount',
        'payment_or_number',
        'or_date',
        'additional_requests',
        'remarks',
        'captured_by',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'detailed_checkin_datetime' => 'datetime',
            'detailed_checkout_datetime' => 'datetime',
            'payment_amount' => 'decimal:2',
            'additional_requests' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function capturedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
