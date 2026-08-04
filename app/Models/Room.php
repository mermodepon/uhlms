<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    public function roomHolds(): HasMany
    {
        return $this->hasMany(RoomHold::class);
    }

    // ─── Occupancy Helpers ────────────────────────────────────────────────────

    /**
     * Number of guests currently checked in to this room.
     * 1 guest = 1 slot regardless of room type.
     */
    public function currentOccupancy(): int
    {
        if (array_key_exists('checked_in_count', $this->attributes)) {
            return (int) $this->attributes['checked_in_count'];
        }

        if ($this->relationLoaded('roomAssignments')) {
            return $this->roomAssignments
                ->where('status', 'checked_in')
                ->count();
        }

        return $this->roomAssignments()
            ->where('status', 'checked_in')
            ->count();
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
        $currentOccupancy = $this->currentOccupancy();

        if ($isPrivate) {
            return $currentOccupancy > 0;
        }

        return $this->capacity > 0 && $currentOccupancy >= $this->capacity;
    }

    /**
     * Number of guest slots still available in this room.
     */
    public function availableSlots(): int
    {
        return max(0, $this->capacity - $this->currentOccupancy());
    }

    /**
     * Date-aware reservation and capacity information for the staff rooms
     * table. Holds are deliberately kept separate from occupancy: private
     * rooms are exclusive, while dorms sell their remaining beds.
     *
     * @return array{label:string,color:string,current_holds:int,upcoming_holds:int,held_beds:int,available_beds:int}
     */
    public function reservationAvailability(?Carbon $date = null): array
    {
        $date = ($date ?? Carbon::today())->startOfDay();
        $this->loadMissing('roomType');

        $holds = $this->relationLoaded('roomHolds')
            ? $this->roomHolds
            : $this->roomHolds()->advance()->active()->get();
        $holds = $holds->filter(fn (RoomHold $hold): bool => $hold->isAdvance() && ! $hold->isExpired());
        $currentHolds = $holds->filter(fn (RoomHold $hold): bool => $hold->isCurrent($date));
        $upcomingHolds = $holds->filter(fn (RoomHold $hold): bool => $hold->timelineStatus($date) === 'upcoming');
        $checkedIn = $this->currentOccupancy();

        if ($this->roomType?->isPrivate()) {
            if ($checkedIn > 0) {
                return ['label' => 'Checked in', 'color' => 'danger', 'current_holds' => $currentHolds->count(), 'upcoming_holds' => $upcomingHolds->count(), 'held_beds' => 0, 'available_beds' => 0];
            }

            if ($currentHolds->isNotEmpty()) {
                $until = $currentHolds->max(fn (RoomHold $hold) => Carbon::parse($hold->hold_to)->toDateString());

                return ['label' => 'Reserved until '.Carbon::parse($until)->format('M d, Y'), 'color' => 'warning', 'current_holds' => $currentHolds->count(), 'upcoming_holds' => $upcomingHolds->count(), 'held_beds' => 0, 'available_beds' => 0];
            }

            if ($upcomingHolds->isNotEmpty()) {
                $next = $upcomingHolds->sortBy('hold_from')->first();

                return ['label' => 'Upcoming hold: '.Carbon::parse($next->hold_from)->format('M d').'–'.Carbon::parse($next->hold_to)->format('M d'), 'color' => 'info', 'current_holds' => 0, 'upcoming_holds' => $upcomingHolds->count(), 'held_beds' => 0, 'available_beds' => max(0, (int) $this->capacity)];
            }

            return ['label' => 'No current hold', 'color' => 'success', 'current_holds' => 0, 'upcoming_holds' => 0, 'held_beds' => 0, 'available_beds' => max(0, (int) $this->capacity)];
        }

        $heldBeds = (int) $currentHolds->sum(fn (RoomHold $hold): int => max(1, (int) ($hold->held_guest_count ?? 1)));
        $availableBeds = max(0, (int) $this->capacity - $checkedIn - $heldBeds);

        return [
            'label' => $availableBeds === 0
                ? "Fully allocated ({$checkedIn} checked in · {$heldBeds} held)"
                : "{$checkedIn} checked in · {$heldBeds} held · {$availableBeds} available",
            'color' => $availableBeds === 0 ? 'warning' : 'success',
            'current_holds' => $currentHolds->count(),
            'upcoming_holds' => $upcomingHolds->count(),
            'held_beds' => $heldBeds,
            'available_beds' => $availableBeds,
        ];
    }

    public function hasCurrentPrivateHold(?Carbon $date = null): bool
    {
        $this->loadMissing('roomType');

        return (bool) ($this->roomType?->isPrivate() ?? false)
            && $this->reservationAvailability($date)['current_holds'] > 0;
    }

    /**
     * Whether the room can accept a new guest right now.
     */
    public function isAvailable(): bool
    {
        if (! $this->is_active || $this->isFull()) {
            return false;
        }

        // Dorm rooms can accept guests while occupied (not yet at full capacity)
        $this->loadMissing('roomType');
        if (! ($this->roomType?->isPrivate() ?? false)) {
            return in_array($this->status, ['available', 'occupied']);
        }

        return $this->status === 'available';
    }

    /**
     * Recalculate and persist this room's occupancy status.
     * Bases the result on the current count of checked-in assignments.
     * No-op when the room is under maintenance or inactive.
     * Also preserves 'reserved' status if an active advance hold exists.
     */
    public function recalculateStatus(): void
    {
        $newStatus = $this->calculatedOperationalStatus();

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }

    /** Determine the persisted operational status without changing the room. */
    public function calculatedOperationalStatus(): string
    {
        if (in_array($this->status, ['maintenance', 'inactive'], true)) {
            return $this->status;
        }

        $this->loadMissing('roomType');

        $checkedInCount = $this->roomAssignments()->where('status', 'checked_in')->count();

        // A checked-in guest always takes precedence over a remaining hold.
        if (($this->roomType?->isPrivate() ?? false) && $checkedInCount > 0) {
            return 'occupied';
        }

        // Private rooms are exclusive, so a current advance hold reserves the
        // whole room. Public dorms keep selling any remaining beds.
        $hasAdvanceHold = $this->roomHolds()
            ->advance()
            ->where('hold_from', '<=', now()->toDateString())
            ->where('hold_to', '>', now()->toDateString())
            ->exists();

        if (($this->roomType?->isPrivate() ?? false) && $hasAdvanceHold) {
            return 'reserved';
        }

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

        return $newStatus;
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
