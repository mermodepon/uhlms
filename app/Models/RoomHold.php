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
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'hold_from' => 'date',
            'hold_to' => 'date',
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
     * Only short-term holds (from preparePendingPayment).
     */
    public function scopeShortTerm($query)
    {
        return $query->where('hold_type', 'short_term');
    }

    /**
     * Holds that have expired (short-term only).
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
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

    public function isAdvance(): bool
    {
        return $this->hold_type === 'advance';
    }

    public function isShortTerm(): bool
    {
        return $this->hold_type === 'short_term';
    }
}
