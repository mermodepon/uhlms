<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RoomHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'reservation_id',
        'hold_from',
        'hold_to',
        'hold_type',
        'held_guest_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'hold_from' => 'date',
            'hold_to' => 'date',
            'held_guest_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Only advance holds (long-term, no expiry).
     */
    public function scopeAdvance($query)
    {
        return $query->where('hold_type', 'advance');
    }

    /**
     * Holds that conflict with a given date range.
     * A conflict exists when: hold_from < requested_checkout AND hold_to > requested_checkin
     */
    public function scopeConflictingWith($query, Carbon $checkIn, Carbon $checkOut)
    {
        return $query->where('hold_from', '<', $checkOut->toDateString())
            ->where('hold_to', '>', $checkIn->toDateString());
    }

    /**
     * Holds that are currently active (not expired).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::parse($this->expires_at)->isPast();
    }

    /**
     * Return the operational timeline state for staff displays.
     *
     * An advance hold intentionally has no expiry, so "active" alone is not
     * enough to tell whether its stay is still current.
     */
    public function timelineStatus(?Carbon $date = null): string
    {
        $date = ($date ?? Carbon::today())->startOfDay();

        if ($this->isExpired()) {
            return 'expired';
        }

        if (Carbon::parse($this->hold_to)->startOfDay()->lte($date)) {
            return 'past';
        }

        if (Carbon::parse($this->hold_from)->startOfDay()->gt($date)) {
            return 'upcoming';
        }

        return 'current';
    }

    public function isCurrent(?Carbon $date = null): bool
    {
        return $this->timelineStatus($date) === 'current';
    }

    public function isAdvance(): bool
    {
        return $this->hold_type === 'advance';
    }
}
