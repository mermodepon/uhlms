<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRoomRequest extends Model
{
    protected $fillable = [
        'reservation_id',
        'room_type_id',
        'requested_capacity',
        'requested_room_count',
        'occupant_count',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_room_count' => 'integer',
            'requested_capacity' => 'integer',
            'occupant_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function getSummaryLabelAttribute(): string
    {
        $roomCount = max(1, (int) $this->requested_room_count);
        $guestCount = max(1, (int) $this->occupant_count);
        $roomWord = $roomCount === 1 ? 'room' : 'rooms';
        $guestWord = $guestCount === 1 ? 'guest' : 'guests';
        $typeName = $this->roomType?->name ?? 'Room type';
        $capacity = $this->requested_capacity ? " (up to {$this->requested_capacity} guests)" : '';

        return "{$roomCount} {$typeName}{$capacity} {$roomWord}, {$guestCount} {$guestWord}";
    }
}
