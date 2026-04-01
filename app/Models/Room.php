<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_number',
        'room_type_id',
        'floor_id',
        'capacity',
        'status',
        'description',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }


    // ─── Occupancy Helpers ────────────────────────────────────────────────────

    /**
     * Number of guests currently checked in to this room.
     * 1 guest = 1 slot regardless of room type.
     */
    public function currentOccupancy(): int
    {
        return $this->roomAssignments()->where('status', 'checked_in')->count();
    }

    /**
     * Whether the room has reached its maximum capacity.
     *
     * - Private rooms: full as soon as ANY guest is checked in (exclusive to one reservation).
     * - Public/dorm rooms: full only when ALL slots (capacity) are taken.
     */
    public function isFull(): bool
    {
        $isPrivate = $this->roomType?->isPrivate() ?? false;

        if ($isPrivate) {
            return $this->roomAssignments()->where('status', 'checked_in')->exists();
        }

        return $this->capacity > 0 && $this->currentOccupancy() >= $this->capacity;
    }

    /**
     * Number of guest slots still available in this room.
     */
    public function availableSlots(): int
    {
        return max(0, $this->capacity - $this->currentOccupancy());
    }

    /**
     * Whether the room can accept a new guest right now.
     */
    public function isAvailable(): bool
    {
        return $this->is_active
            && in_array($this->status, ['available'])
            && ! $this->isFull();
    }

    /**
     * Recalculate and persist this room's occupancy status.
     * Bases the result on the current count of checked-in assignments.
     * No-op when the room is under maintenance or inactive.
     */
    public function recalculateStatus(): void
    {
        if (in_array($this->status, ['maintenance', 'inactive'], true)) {
            return;
        }

        $this->loadMissing('roomType');
        $checkedInCount = $this->roomAssignments()->where('status', 'checked_in')->count();

        if ($this->roomType?->isPrivate()) {
            // Private room: occupied as soon as any guest is checked in
            $newStatus = $checkedInCount > 0 ? 'occupied' : 'available';
        } else {
            // Dorm room: still accepts guests until all slots are taken;
            // only mark occupied when at or over full capacity
            $newStatus = ($this->capacity > 0 && $checkedInCount >= $this->capacity)
                ? 'occupied'
                : 'available';
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Automatically open the next inactive room of the same type
     * when this room becomes full.
     */
    public function autoOpenNextRoomIfFull(): void
    {
        if (! $this->isFull()) {
            return;
        }

        // Mark this room as fully occupied
        $this->update(['status' => 'occupied']);

        // Find the next waiting room of the same type and open it
        $nextRoom = static::query()
            ->where('room_type_id', $this->room_type_id)
            ->where('status', 'inactive')
            ->where('is_active', true)
            ->orderBy('room_number')
            ->first();

        $nextRoom?->update(['status' => 'available']);
    }
}
