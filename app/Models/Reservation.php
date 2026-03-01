<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Reservation extends Model
{
    protected $fillable = [
        'reference_number',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_address',
        'guest_organization',
        'preferred_room_type_id',
        'check_in_date',
        'check_out_date',
        'number_of_occupants',
        'purpose',
        'special_requests',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reservation) {
            if (empty($reservation->reference_number)) {
                $reservation->reference_number = 'RES-' . strtoupper(Str::random(8));
            }
        });
    }

    public function preferredRoomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'preferred_room_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function stayLogs(): HasMany
    {
        return $this->hasMany(StayLog::class);
    }

    public function getNightsAttribute(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'declined' => 'danger',
            'cancelled' => 'gray',
            'checked_in' => 'success',
            'checked_out' => 'gray',
            default => 'gray',
        };
    }
}
