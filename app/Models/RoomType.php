<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_rate',
        'pricing_type',
        'images',
        'virtual_tour_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'base_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function availableRooms(): HasMany
    {
        return $this->hasMany(Room::class)->where('status', 'available')->where('is_active', true);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_room_type')->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'preferred_room_type_id');
    }

    /**
     * Check if this room type uses per-person pricing
     */
    public function isPerPersonPricing(): bool
    {
        return $this->pricing_type === 'per_person';
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
        $price = '₱' . number_format($this->base_rate, 0);
        
        if ($this->isPerPersonPricing()) {
            return $price . '/person/night';
        }
        
        return $price . '/night';
    }

    /**
     * Calculate total rate based on pricing type
     * 
     * @param int $nights Number of nights
     * @param int $guests Number of guests (only used for per-person pricing)
     * @return float
     */
    public function calculateRate(int $nights = 1, int $guests = 1): float
    {
        if ($this->isPerPersonPricing()) {
            return $this->base_rate * $guests * $nights;
        }
        
        return $this->base_rate * $nights;
    }
}
