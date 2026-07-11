<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationAlternativeOffer extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'reservation_id', 'reservation_room_request_id', 'offered_room_type_id',
        'room_ids', 'original_total', 'quoted_total', 'message', 'status',
        'expires_at', 'responded_at', 'proposed_by',
    ];

    protected function casts(): array
    {
        return [
            'room_ids' => 'array',
            'original_total' => 'decimal:2',
            'quoted_total' => 'decimal:2',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function requestLine(): BelongsTo { return $this->belongsTo(ReservationRoomRequest::class, 'reservation_room_request_id'); }
    public function offeredRoomType(): BelongsTo { return $this->belongsTo(RoomType::class, 'offered_room_type_id'); }
    public function proposer(): BelongsTo { return $this->belongsTo(User::class, 'proposed_by'); }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at?->isFuture();
    }
}
