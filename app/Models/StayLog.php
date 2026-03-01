<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StayLog extends Model
{
    protected $fillable = [
        'reservation_id',
        'room_id',
        'checked_in_at',
        'checked_in_by',
        'checked_out_at',
        'checked_out_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }
}
