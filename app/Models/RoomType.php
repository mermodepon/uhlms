<?php

namespace App\Models;

use App\Services\RoomHoldService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_rate',
        'pricing_type',
        'room_sharing_type',
        'images',
        'is_active',
    ];

    protected $casts = [
        'images' => 'array',
        'base_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function availableRooms(): HasMany
    {
        return $this->hasMany(Room::class)->where('status', 'available')->where('is_active', true);
    }

    /**
     * Get rooms available for a specific date range (considers room holds).
     *
     * @return \Illuminate\Support\Collection<int, Room>
     */
    public function availableRoomsForDates(Carbon $checkIn, Carbon $checkOut): \Illuminate\Support\Collection
    {
        return app(RoomHoldService::class)->getAvailableRooms($this, $checkIn, $checkOut);
    }

    /**
     * Get count of rooms available for a specific date range.
     */
    public function availableRoomsCountForDates(Carbon $checkIn, Carbon $checkOut): int
    {
        return app(RoomHoldService::class)->getAvailableRoomCount($this, $checkIn, $checkOut);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_room_type')->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'preferred_room_type_id');
    }

    public function tourWaypoints(): HasMany
    {
        return $this->hasMany(TourWaypoint::class, 'linked_room_type_id');
    }

    /**
     * Check if this room type uses per-person pricing
     */
    public function isPerPersonPricing(): bool
    {
        return $this->pricing_type === 'per_person';
    }

    /**
     * Private rooms are exclusive to a single reservation;
     * once any bed is occupied the entire room is locked for that reservation.
     */
    public function isPrivate(): bool
    {
        return $this->room_sharing_type === 'private';
    }

    /**
     * Public rooms follow dormitory-style sharing:
     * multiple guests can occupy the same room up to full capacity.
     */
    public function isPublic(): bool
    {
        return $this->room_sharing_type !== 'private';
    }

    /**
     * Get the maximum capacity from rooms of this type
     * Returns 1 if no rooms exist
     */
    public function getCapacityAttribute(): int
    {
        return $this->rooms()->max('capacity') ?? 1;
    }

    /**
     * Get formatted pricing display
     */
    public function getFormattedPrice(): string
    {
        $price = '₱'.number_format($this->base_rate, 0);

        if ($this->isPerPersonPricing()) {
            return $price.'/person/night';
        }

        return $price.'/night';
    }

    /**
     * Calculate total rate based on pricing type
     *
     * @param  int  $nights  Number of nights
     * @param  int  $guests  Number of guests (only used for per-person pricing)
     */
    public function calculateRate(int $nights = 1, int $guests = 1): float
    {
        if ($this->isPerPersonPricing()) {
            return $this->base_rate * $guests * $nights;
        }

        return $this->base_rate * $nights;
    }
}
