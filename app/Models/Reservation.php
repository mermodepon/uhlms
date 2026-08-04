<?php

namespace App\Models;

use App\Services\ReservationAccountLinker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Reservation extends Model
{
    /**
     * Single source of truth for reservation status presentation.
     */
    private const STATUS_PRESENTATIONS = [
        'pending' => ['label' => 'Pending Review', 'admin_label' => 'Pending', 'hex' => '#fbbf24', 'badge_text' => '#422006', 'filament_color' => 'warning', 'guidance' => 'Your request is waiting for staff review. Estimated processing time is 1-2 business days. Please watch your email for approval or follow-up instructions.'],
        'awaiting_alternative_confirmation' => ['label' => 'Alternative Offer Pending', 'admin_label' => 'Awaiting Alternative Confirmation', 'hex' => '#f59e0b', 'badge_text' => '#422006', 'filament_color' => 'warning', 'guidance' => 'We have reserved a possible room alternative for you. Please check your email and accept or decline the offer before it expires.'],
        'approved' => ['label' => 'Approved', 'admin_label' => 'Approved', 'hex' => '#919F02', 'badge_text' => '#ffffff', 'filament_color' => 'info', 'guidance' => 'Your reservation request has been approved. Staff will reserve room space before online payment becomes available.'],
        'confirmed' => ['label' => 'Confirmed', 'admin_label' => 'Confirmed', 'hex' => '#10B981', 'badge_text' => '#ffffff', 'filament_color' => 'success', 'guidance' => 'Room space has been reserved for your stay. Please keep monitoring your email for payment reminders or arrival instructions.'],
        'declined' => ['label' => 'Declined', 'admin_label' => 'Declined', 'hex' => '#EF4444', 'badge_text' => '#ffffff', 'filament_color' => 'danger', 'guidance' => 'This reservation request was declined. Please contact the homestay staff if you need clarification or would like to submit a new request.'],
        'cancelled' => ['label' => 'Cancelled', 'admin_label' => 'Cancelled', 'hex' => '#6B7280', 'badge_text' => '#ffffff', 'filament_color' => 'gray', 'guidance' => 'This reservation has been cancelled. Contact staff if you believe this was made in error.'],
        'checked_in' => ['label' => 'Checked In', 'admin_label' => 'Checked In', 'hex' => '#16a34a', 'badge_text' => '#ffffff', 'filament_color' => 'success', 'guidance' => 'You are currently checked in. If you need help during your stay, please contact the homestay staff.'],
        'checked_out' => ['label' => 'Checked Out', 'admin_label' => 'Checked Out', 'hex' => '#94a3b8', 'badge_text' => '#0f172a', 'filament_color' => 'gray', 'guidance' => 'This reservation has been completed. Thank you for staying with us.'],
    ];

    private const GUEST_DASHBOARD_CARDS = [
        'upcoming' => ['label' => 'Upcoming', 'accent' => '#919F02'],
        'pending' => ['label' => 'Pending', 'accent' => '#fbbf24', 'statuses' => ['pending']],
        'awaiting_alternative_confirmation' => ['label' => 'Alternative Offer Pending', 'accent' => '#f59e0b', 'statuses' => ['awaiting_alternative_confirmation']],
        'active' => ['label' => 'Active', 'accent' => '#10B981', 'statuses' => ['approved', 'confirmed', 'checked_in']],
        'completed' => ['label' => 'Completed', 'accent' => '#94a3b8', 'statuses' => ['checked_out']],
    ];

    protected $fillable = [
        'reference_number',
        'guest_account_id',
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

        // Staff-created or edited reservations can be linked as soon as their guest email
        // matches an active, verified guest account. The linker never replaces an existing link.
        static::saved(function (self $reservation) {
            app(ReservationAccountLinker::class)->link($reservation);
        });
    }

    public static function statusPresentation(?string $status): array
    {
        return self::STATUS_PRESENTATIONS[$status] ?? [
            'label' => filled($status) ? str($status)->replace('_', ' ')->title()->toString() : 'Unknown',
            'admin_label' => filled($status) ? str($status)->replace('_', ' ')->title()->toString() : 'Unknown',
            'hex' => '#6B7280',
            'badge_text' => '#ffffff',
            'filament_color' => 'gray',
            'guidance' => 'Please monitor this page and your email for updates to your reservation.',
        ];
    }

    public static function statusOptions(bool $adminLabels = true): array
    {
        return collect(self::STATUS_PRESENTATIONS)
            ->mapWithKeys(fn (array $presentation, string $status) => [$status => $presentation[$adminLabels ? 'admin_label' : 'label']])
            ->all();
    }

    public static function guestDashboardCards(): array
    {
        return self::GUEST_DASHBOARD_CARDS;
    }

    public function preferredRoomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'preferred_room_type_id');
    }

    public function guestAccount(): BelongsTo
    {
        return $this->belongsTo(GuestAccount::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(ReservationFeedback::class);
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

    public function roomRequests(): HasMany
    {
        return $this->hasMany(ReservationRoomRequest::class)->orderBy('sort_order');
    }

    public function alternativeOffers(): HasMany
    {
        return $this->hasMany(ReservationAlternativeOffer::class);
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

    /**
     * Return the financial summary appropriate for guests.
     *
     * Charges are finalized at check-in (or when an alternative offer is
     * accepted). Until then, a deposit belongs against the requested-room
     * estimate, not the empty accounting ledger.
     *
     * @return array{is_finalized: bool, total: float, paid: float, remaining: float, status_label: string, balance_label: string, note: ?string}
     */
    public function guestPaymentSummary(): array
    {
        $hasFinalizedCharges = $this->charges()->exists();
        $total = $hasFinalizedCharges
            ? (float) $this->charges()->sum('amount')
            : $this->calculateRequestedRoomAmount();
        $paid = (float) $this->payments()->where('status', 'posted')->sum('amount');
        $remaining = max(0, $total - $paid);

        if ($hasFinalizedCharges) {
            return [
                'is_finalized' => true,
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining,
                'status_label' => $total > 0 && $remaining <= 0
                    ? 'Paid'
                    : ($paid > 0 ? 'Partially paid' : 'Payment pending'),
                'balance_label' => 'Remaining balance',
                'note' => null,
            ];
        }

        return [
            'is_finalized' => false,
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'status_label' => $paid > 0
                ? ($remaining > 0 ? 'Deposit received' : 'Payment received')
                : 'Payment pending',
            'balance_label' => 'Estimated remaining balance',
            'note' => 'The final balance is confirmed at check-in and may reflect approved add-ons, discounts, or finalized charges.',
        ];
    }

    public function getNightsAttribute(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    public function getStatusColorAttribute(): string
    {
        return self::statusPresentation($this->status)['filament_color'];
    }

    public function canReceiveFeedbackFrom(GuestAccount $account): bool
    {
        return $account->hasVerifiedEmail()
            && (int) $this->guest_account_id === (int) $account->id
            && $this->status === 'checked_out'
            && ! $this->feedback()->exists();
    }

    /**
     * Generate the guest payment link URL.
     */
    public function generatePaymentLink(bool $absolute = true): ?string
    {
        if (empty($this->payment_link_token)) {
            return null;
        }

        return route('guest.payment.show', ['token' => $this->payment_link_token], $absolute);
    }

    /**
     * Generate a signed guest tracking link that expires automatically.
     */
    public function generateGuestTrackingUrl(bool $absolute = true): string
    {
        return URL::temporarySignedRoute(
            'guest.track.secure',
            $this->resolveTrackingLinkExpiry(),
            ['reservation' => $this->id],
            $absolute
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
     * Whether the guest can currently use the online payment link.
     */
    public function canAcceptGuestPayment(): bool
    {
        return in_array($this->status, ['approved', 'confirmed'], true)
            && $this->hasActiveAdvanceHold();
    }

    public function hasActiveAdvanceHold(): bool
    {
        if ($this->relationLoaded('roomHolds')) {
            return $this->roomHolds
                ->where('hold_type', 'advance')
                ->contains(fn ($hold) => ! $hold->isExpired());
        }

        return $this->roomHolds()
            ->advance()
            ->active()
            ->exists();
    }

    /**
     * Start or refresh the guest payment link validity window.
     */
    public function issueGuestPaymentLink(bool $rotateToken = false, ?Carbon $expiresAt = null): self
    {
        if ($rotateToken || empty($this->payment_link_token)) {
            $this->payment_link_token = (string) Str::uuid();
        }

        $this->payment_link_expires_at = $expiresAt ?? $this->resolvePaymentLinkExpiry();

        return $this;
    }

    protected function resolvePaymentLinkExpiry(): Carbon
    {
        $defaultExpiry = now()->addHours(48);

        if (! $this->check_out_date) {
            return $defaultExpiry;
        }

        $stayEnd = $this->check_out_date->copy()->endOfDay();

        return $stayEnd->lessThan($defaultExpiry) ? $stayEnd : $defaultExpiry;
    }

    /**
     * Calculate the deposit amount for online payment.
     * Uses reservation-specific percentage or global default.
     */
    public function calculateDepositAmount(): float
    {
        // First, try to use actual charges if they exist
        $totalCharges = $this->balance_due + $this->payments_total;

        // If no charges are calculated yet, estimate from requested room lines.
        if ($totalCharges <= 0) {
            $totalCharges = $this->calculateRequestedRoomAmount();
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

        // If no charges are calculated yet, estimate from requested room lines.
        if ($totalCharges <= 0) {
            $totalCharges = $this->calculateRequestedRoomAmount();
        }

        return round($totalCharges, 2);
    }

    public function calculateRequestedRoomAmount(): float
    {
        $nights = max(1, (int) ($this->nights ?? 1));
        $requests = $this->getEffectiveRoomRequests();

        if ($requests->isEmpty()) {
            return 0.0;
        }

        return round((float) $requests->sum(function (ReservationRoomRequest $request) use ($nights): float {
            $roomType = $request->roomType;
            if (! $roomType) {
                return 0.0;
            }

            $occupants = max(1, (int) $request->occupant_count);
            $rooms = max(1, (int) $request->requested_room_count);

            if ($roomType->isPerPersonPricing()) {
                return (float) $roomType->base_rate * $occupants * $nights;
            }

            return (float) $roomType->base_rate * $rooms * $nights;
        }), 2);
    }

    public function getRequestedRoomSummaryAttribute(): string
    {
        $requests = $this->getEffectiveRoomRequests();

        if ($requests->isEmpty()) {
            return 'To be assigned';
        }

        return $requests
            ->map(fn (ReservationRoomRequest $request): string => $request->summary_label)
            ->filter()
            ->implode('; ');
    }

    /**
     * Return persisted request lines, or synthesize one for older reservations.
     *
     * @return \Illuminate\Support\Collection<int, ReservationRoomRequest>
     */
    public function getEffectiveRoomRequests()
    {
        $requests = $this->relationLoaded('roomRequests')
            ? $this->roomRequests
            : $this->roomRequests()->with('roomType')->get();

        if ($requests->isNotEmpty()) {
            return $requests->loadMissing('roomType')->values();
        }

        if (! $this->preferred_room_type_id) {
            return collect();
        }

        $request = new ReservationRoomRequest([
            'reservation_id' => $this->id,
            'room_type_id' => $this->preferred_room_type_id,
            'requested_room_count' => 1,
            'occupant_count' => max(1, (int) ($this->number_of_occupants ?? 1)),
            'sort_order' => 0,
        ]);
        $request->setRelation('roomType', $this->preferredRoomType);

        return collect([$request]);
    }

    /**
     * Persist the fallback room request used by reservations created before
     * reservation_room_requests was introduced. Actions that need to refer to
     * a request line (such as an alternative offer) must use a real record.
     */
    public function ensureRoomRequests()
    {
        $requests = $this->roomRequests()->with('roomType')->get();

        if ($requests->isNotEmpty() || ! $this->preferred_room_type_id) {
            return $requests;
        }

        $this->roomRequests()->create([
            'room_type_id' => $this->preferred_room_type_id,
            'requested_room_count' => 1,
            'occupant_count' => max(1, (int) ($this->number_of_occupants ?? 1)),
            'sort_order' => 0,
        ]);

        return $this->roomRequests()->with('roomType')->get();
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

    /**
     * Get payment gateway status label (optimized for table display).
     * Expects payments to be eager-loaded.
     */
    public function getPaymentGatewayStatusAttribute(): string
    {
        // Check for full payment (is_deposit = false)
        $fullPayment = $this->relationLoaded('payments')
            ? $this->payments->where('gateway', 'paymongo')
                ->where('is_deposit', false)
                ->where('gateway_status', 'paid')
                ->isNotEmpty()
            : $this->payments()
                ->where('gateway', 'paymongo')
                ->where('is_deposit', false)
                ->where('gateway_status', 'paid')
                ->exists();

        if ($fullPayment) {
            return 'Online: Fully Paid';
        }

        // Check for deposit payment (is_deposit = true)
        $depositPayment = $this->relationLoaded('payments')
            ? $this->payments->where('gateway', 'paymongo')
                ->where('is_deposit', true)
                ->where('gateway_status', 'paid')
                ->isNotEmpty()
            : $this->payments()
                ->where('gateway', 'paymongo')
                ->where('is_deposit', true)
                ->where('gateway_status', 'paid')
                ->exists();

        if ($depositPayment) {
            return 'Online: Deposit Paid';
        }

        // Check for pending gateway payments
        $pendingPayment = $this->relationLoaded('payments')
            ? $this->payments->where('gateway', 'paymongo')
                ->where('gateway_status', 'pending')
                ->isNotEmpty()
            : $this->payments()
                ->where('gateway', 'paymongo')
                ->where('gateway_status', 'pending')
                ->exists();

        if ($pendingPayment) {
            return 'Online: Pending';
        }

        return 'Manual';
    }

    /**
     * Get payment gateway status color (optimized for table display).
     * Expects payments to be eager-loaded.
     */
    public function getPaymentGatewayColorAttribute(): string
    {
        // Full payment = green
        $fullPayment = $this->relationLoaded('payments')
            ? $this->payments->where('gateway', 'paymongo')
                ->where('is_deposit', false)
                ->where('gateway_status', 'paid')
                ->isNotEmpty()
            : $this->payments()
                ->where('gateway', 'paymongo')
                ->where('is_deposit', false)
                ->where('gateway_status', 'paid')
                ->exists();

        if ($fullPayment) {
            return 'success';
        }

        // Deposit payment = yellow/warning
        $depositPayment = $this->relationLoaded('payments')
            ? $this->payments->where('gateway', 'paymongo')
                ->where('is_deposit', true)
                ->where('gateway_status', 'paid')
                ->isNotEmpty()
            : $this->payments()
                ->where('gateway', 'paymongo')
                ->where('is_deposit', true)
                ->where('gateway_status', 'paid')
                ->exists();

        if ($depositPayment) {
            return 'warning';
        }

        // Pending = blue/info
        $pendingPayment = $this->relationLoaded('payments')
            ? $this->payments->where('gateway', 'paymongo')
                ->where('gateway_status', 'pending')
                ->isNotEmpty()
            : $this->payments()
                ->where('gateway', 'paymongo')
                ->where('gateway_status', 'pending')
                ->exists();

        if ($pendingPayment) {
            return 'info';
        }

        return 'gray';
    }

    /**
     * Get room display data (optimized for table display).
     * Expects roomAssignments, roomHolds relationships to be eager-loaded.
     *
     * @return array{rooms: array<string>, color: string, tooltip: string|null}
     */
    public function getRoomDisplayInfoAttribute(): array
    {
        // Priority 1: Actual room assignments (for checked-in/out)
        if (in_array($this->status, ['checked_in', 'checked_out'], true)) {
            $assignedRooms = $this->relationLoaded('roomAssignments')
                ? $this->roomAssignments->pluck('room.room_number')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray()
                : $this->roomAssignments()
                    ->with('room')
                    ->get()
                    ->pluck('room.room_number')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

            if (!empty($assignedRooms)) {
                return [
                    'rooms' => $assignedRooms,
                    'color' => 'success',
                    'tooltip' => 'Room assignment (occupied)'
                ];
            }
        }

        // Priority 2: Advance holds (for approved/confirmed)
        if (in_array($this->status, ['approved', 'confirmed'], true)) {
            $holds = $this->relationLoaded('roomHolds')
                ? $this->roomHolds->where('hold_type', 'advance')->filter(fn ($h) => $h->room)
                : $this->roomHolds()->advance()->with('room')->get();

            if ($holds->isNotEmpty()) {
                $heldRooms = $holds
                    ->pluck('room.room_number')
                    ->filter()
                    ->unique()
                    ->map(fn ($r) => "[Held] {$r}")
                    ->values()
                    ->toArray();

                if (!empty($heldRooms)) {
                    return [
                        'rooms' => $heldRooms,
                        'color' => 'info',
                        'tooltip' => 'Advance hold (reserved)',
                    ];
                }
            }
        }

        return ['rooms' => [], 'color' => 'gray', 'tooltip' => null];
    }

    /**
     * Get discount information (optimized for table display).
     * Expects charges relationship to be eager-loaded.
     *
     * @return array{label: string, applied: bool, amount: float}
     */
    public function getDiscountInfoAttribute(): array
    {
        $charges = $this->relationLoaded('charges')
            ? $this->charges
            : $this->charges()->get();

        $discountCharges = $charges->where('charge_type', 'discount');

        $discountTotal = (float) abs($discountCharges->sum('amount'));

        $discountTypes = $discountCharges
            ->flatMap(function ($charge) {
                $types = data_get($charge->meta, 'discount_types', []);
                if (is_array($types) && !empty($types)) {
                    return $types;
                }
                // Fallback: legacy 'discount_type' (singular string)
                $legacy = data_get($charge->meta, 'discount_type');

                return $legacy ? [(string) $legacy] : [];
            })
            ->filter()
            ->map(fn ($type) => $this->formatDiscountLabel((string) $type))
            ->filter()
            ->unique()
            ->values();

        $discountApplied = $discountTotal > 0 || $discountTypes->isNotEmpty();
        $label = $discountApplied ? $discountTypes->implode(', ') : 'No';

        return [
            'label' => $label,
            'applied' => $discountApplied,
            'amount' => $discountTotal,
        ];
    }

    /**
     * Format discount type label for display.
     */
    private function formatDiscountLabel(string $raw): string
    {
        return match (strtolower($raw)) {
            'pwd' => 'PWD',
            'senior_citizen', 'senior' => 'Senior Citizen',
            'student' => 'Student',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
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
