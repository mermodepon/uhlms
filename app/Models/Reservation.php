<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Reservation extends Model
{
    protected $fillable = [
        'reference_number',
        'guest_name',
        'guest_last_name',
        'guest_first_name',
        'guest_middle_initial',
        'guest_gender',
        'guest_email',
        'guest_phone',
        'guest_address',
        'preferred_room_type_id',
        'check_in_date',
        'check_out_date',
        'number_of_occupants',
        'num_male_guests',
        'num_female_guests',
        'purpose',
        'special_requests',
        'status',
        'admin_notes',
        'checkin_hold_payload',
        'checkin_hold_started_at',
        'checkin_hold_expires_at',
        'checkin_hold_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'checkin_hold_payload' => 'array',
            'checkin_hold_started_at' => 'datetime',
            'checkin_hold_expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reservation) {
            if (empty($reservation->reference_number)) {
                $currentYear = now()->year;

                // Atomically increment the permanent counter for this year.
                // This ensures deleted reservations never recycle their numbers.
                DB::table('reservation_sequences')->upsert(
                    ['year' => $currentYear, 'last_sequence' => 1],
                    ['year'],
                    ['last_sequence' => DB::raw('last_sequence + 1')]
                );

                $nextSequence = DB::table('reservation_sequences')
                    ->where('year', $currentYear)
                    ->value('last_sequence');

                $sequenceNumber = str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

                $reservation->reference_number = $currentYear . '-' . $sequenceNumber;
            }
        });

        // Automatically populate guest_name from separate name fields
        static::saving(function (self $reservation) {
            if ($reservation->guest_first_name || $reservation->guest_last_name) {
                $reservation->guest_name = trim(
                    $reservation->guest_first_name . ' ' . 
                    ($reservation->guest_middle_initial ?? '') . ' ' . 
                    $reservation->guest_last_name
                );
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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
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
            'pending_payment' => 'warning',
            'declined' => 'danger',
            'cancelled' => 'gray',
            'checked_in' => 'success',
            'checked_out' => 'gray',
            default => 'gray',
        };
    }
}
