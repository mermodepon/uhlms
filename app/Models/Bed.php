<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bed extends Model
{
    protected $fillable = [
        'room_id',
        'bed_number',
        'status',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    /**
     * Mark this bed as occupied.
     */
    public function occupy(): void
    {
        $this->update(['status' => 'occupied']);
    }

    /**
     * Mark this bed as available (freed).
     */
    public function free(): void
    {
        $this->update(['status' => 'available']);
    }
}
