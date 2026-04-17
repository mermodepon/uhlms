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
        'guest_age',
        'guest_email',
        'guest_phone',
        'guest_address',
        'preferred_room_type_id',
        'billing_guest_id',
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
        'addons_total',
        'payments_total',
        'balance_due',
        'payment_status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'guest_age' => 'integer',
            'num_male_guests' => 'integer',
            'num_female_guests' => 'integer',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'checkin_hold_payload' => 'array',
            'checkin_hold_started_at' => 'datetime',
            'checkin_hold_expires_at' => 'datetime',
            'addons_total' => 'decimal:2',
            'payments_total' => 'decimal:2',
            'balance_due' => 'decimal:2',
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

                $reservation->reference_number = $currentYear.'-'.$sequenceNumber;
            }
        });

        // Automatically populate guest_name from separate name fields
        static::saving(function (self $reservation) {
            if ($reservation->guest_first_name || $reservation->guest_last_name) {
                $reservation->guest_name = trim(
                    $reservation->guest_first_name.' '.
                    ($reservation->guest_middle_initial ?? '').' '.
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

    public function billingGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'billing_guest_id');
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function checkInSnapshots(): HasMany
    {
        return $this->hasMany(CheckInSnapshot::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ReservationCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReservationPayment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReservationLog::class)->orderBy('logged_at', 'desc');
    }

    public function roomHolds(): HasMany
    {
        return $this->hasMany(RoomHold::class);
    }

    public function refreshFinancialSummary(): void
    {
        $chargesTotal = (float) $this->charges()->sum('amount');
        $addonsTotal = (float) $this->charges()->where('charge_type', 'addon')->sum('amount');
        $paymentsTotal = (float) $this->payments()->where('status', 'posted')->sum('amount');
        $balanceDue = $chargesTotal - $paymentsTotal;

        $paymentStatus = 'pending';
        if ($chargesTotal <= 0 && $paymentsTotal <= 0) {
            $paymentStatus = 'pending';
        } elseif ($balanceDue <= 0) {
            $paymentStatus = 'paid';
        } elseif ($paymentsTotal > 0) {
            $paymentStatus = 'partially_paid';
        }

        $this->update([
            'addons_total' => $addonsTotal,
            'payments_total' => $paymentsTotal,
            'balance_due' => max(0, $balanceDue),
            'payment_status' => $paymentStatus,
        ]);
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
            'confirmed' => 'success',
            'pending_payment' => 'warning',
            'declined' => 'danger',
            'cancelled' => 'gray',
            'checked_in' => 'success',
            'checked_out' => 'gray',
            default => 'gray',
        };
    }
}
