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
        'gender_type',   // 'male' | 'female' | 'any'
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


    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    // ─── Dormitory Occupancy Helpers ─────────────────────────────────────────

    /**
     * Number of beds currently marked as occupied.
     * Falls back to counting active room assignments when no beds are configured.
     */
    public function currentOccupancy(): int
    {
        if ($this->beds()->exists()) {
            return $this->beds()->where('status', 'occupied')->count();
        }

        // Legacy fallback: count current room assignments that are checked in
        return $this->roomAssignments()
            ->whereHas('reservation', fn ($q) => $q->where('status', 'checked_in'))
            ->count();
    }

    /**
     * Whether the room has reached its maximum capacity.
     *
     * - Private room types: full as soon as ANY bed is occupied (exclusive to one reservation).
     * - Public room types:  full only when ALL beds are occupied (dormitory-style sharing).
     */
    public function isFull(): bool
    {
        $isPrivate = $this->roomType?->isPrivate() ?? false;

        if ($this->beds()->exists()) {
            if ($isPrivate) {
                // Private: full once even one bed is occupied
                return $this->beds()->where('status', 'occupied')->exists();
            }
            // Public: full only when no available beds remain
            return $this->beds()->where('status', 'available')->doesntExist();
        }

        // Fallback (no beds configured)
        if ($isPrivate) {
            return $this->roomAssignments()
                ->whereHas('reservation', fn ($q) => $q->where('status', 'checked_in'))
                ->exists();
        }

        return $this->capacity > 0 && $this->currentOccupancy() >= $this->capacity;
    }

    /**
     * Count of beds still available for assignment.
     */
    public function availableBedsCount(): int
    {
        return $this->beds()->where('status', 'available')->count();
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
     * Human-readable label for gender type.
     */
    public function getGenderLabel(): string
    {
        return match ($this->gender_type) {
            'male'   => 'Male',
            'female' => 'Female',
            default  => 'Any',
        };
    }

    /**
     * Automatically open the next inactive room of the same type and gender
     * when this room becomes full. Call after a bed is occupied.
     */
    public function autoOpenNextRoomIfFull(): void
    {
        if (! $this->isFull()) {
            return;
        }

        // Mark this room as fully occupied
        $this->update(['status' => 'occupied']);

        // Find the next waiting room of the same type & gender and open it
        $nextRoom = static::query()
            ->where('room_type_id', $this->room_type_id)
            ->where('gender_type', $this->gender_type)
            ->where('status', 'inactive')
            ->where('is_active', true)
            ->orderBy('room_number')
            ->first();

        $nextRoom?->update(['status' => 'available']);
    }
}
