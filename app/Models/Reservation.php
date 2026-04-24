<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

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
        'approved_at',
        'admin_notes',
        'checkin_hold_payload',
        'checkin_hold_started_at',
        'checkin_hold_expires_at',
        'checkin_hold_by',
        'addons_total',
        'payments_total',
        'balance_due',
        'payment_status',
        'payment_link_token',
        'payment_link_expires_at',
        'deposit_percentage',
        'reviewed_by',
        'reviewed_at',
        'discount_declared',
        'discount_declared_type',
        'discount_verified',
        'discount_verification_notes',
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
            'payment_link_expires_at' => 'datetime',
            'deposit_percentage' => 'decimal:2',
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'discount_declared' => 'boolean',
            'discount_verified' => 'boolean',
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

    /**
     * Extract preferred room ID from special_requests metadata (virtual tour).
     */
    public function getPreferredRoomIdAttribute(): ?int
    {
        if (empty($this->special_requests)) {
            return null;
        }

        // Match: [Preferred Room ID: 123]
        if (preg_match('/\[Preferred Room ID: (\d+)\]/', $this->special_requests, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Get the preferred Room model if it exists and is valid.
     */
    public function getPreferredRoomAttribute(): ?Room
    {
        $roomId = $this->getPreferredRoomIdAttribute();
        if (!$roomId) {
            return null;
        }

        return Room::with('roomType')->find($roomId);
    }

    /**
     * Generate the guest payment link URL.
     */
    public function generatePaymentLink(): ?string
    {
        if (empty($this->payment_link_token)) {
            return null;
        }

        return url("/reserve/pay/{$this->payment_link_token}");
    }

    /**
     * Generate a signed guest tracking link that expires automatically.
     */
    public function generateGuestTrackingUrl(): string
    {
        return URL::temporarySignedRoute(
            'guest.track.secure',
            $this->resolveTrackingLinkExpiry(),
            ['reservation' => $this->id]
        );
    }

    /**
     * Check if the payment link token is still valid (exists and not expired).
     */
    public function isPaymentLinkValid(): bool
    {
        if (empty($this->payment_link_token) || empty($this->payment_link_expires_at)) {
            return false;
        }

        return now()->lessThan($this->payment_link_expires_at);
    }

    /**
     * Calculate the deposit amount for online payment.
     * Uses reservation-specific percentage or global default.
     */
    public function calculateDepositAmount(): float
    {
        // First, try to use actual charges if they exist
        $totalCharges = $this->balance_due + $this->payments_total;

        // If no charges calculated yet (new reservation), estimate based on room type
        if ($totalCharges <= 0 && $this->preferredRoomType) {
            $nights = $this->nights ?? 1;
            $guests = $this->number_of_occupants ?? 1;
            
            // Calculate estimated rate from preferred room type
            $totalCharges = $this->preferredRoomType->calculateRate($nights, $guests);
        }

        if ($totalCharges <= 0) {
            return 0.0;
        }

        $percentage = $this->deposit_percentage ?? Setting::getDefaultDepositPercentage();

        return round($totalCharges * ($percentage / 100), 2);
    }

    /**
     * Calculate the full payment amount for online payment.
     * This is the estimated total charges for the reservation.
     */
    public function calculateFullAmount(): float
    {
        // First, try to use actual charges if they exist
        $totalCharges = $this->balance_due + $this->payments_total;

        // If no charges calculated yet (new reservation), estimate based on room type
        if ($totalCharges <= 0 && $this->preferredRoomType) {
            $nights = $this->nights ?? 1;
            $guests = $this->number_of_occupants ?? 1;

            // Calculate estimated rate from preferred room type
            $totalCharges = $this->preferredRoomType->calculateRate($nights, $guests);
        }

        return round($totalCharges, 2);
    }

    /**
     * Check if this reservation has been fully paid online.
     */
    public function isFullyPaidOnline(): bool
    {
        return $this->payments()
            ->where('gateway', 'paymongo')
            ->where('is_deposit', false)
            ->where('gateway_status', 'paid')
            ->where('status', 'posted')
            ->exists();
    }

    private function resolveTrackingLinkExpiry()
    {
        $terminalExpiryDays = [
            'checked_out' => 30,
            'declined' => 14,
            'cancelled' => 14,
        ];

        if (isset($terminalExpiryDays[$this->status])) {
            return ($this->updated_at ?? now())->copy()->addDays($terminalExpiryDays[$this->status]);
        }

        $baseDate = $this->check_out_date?->copy()->endOfDay() ?? now();

        if ($baseDate->lessThan(now())) {
            $baseDate = now();
        }

        return $baseDate->addDays(30);
    }
}

