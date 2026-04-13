<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TourWaypoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'panorama_image',
        'default_yaw',
        'default_pitch',
        'default_zoom',
        'thumbnail_image',
        'position_order',
        'linked_room_type_id',
        'room_info_yaw',
        'room_info_pitch',
        'description',
        'narration',
        'is_active',
    ];

    protected $casts = [
        'position_order' => 'integer',
        'default_yaw' => 'decimal:4',
        'default_pitch' => 'decimal:4',
        'default_zoom' => 'integer',
        'room_info_yaw' => 'decimal:4',
        'room_info_pitch' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TourWaypoint $waypoint) {
            if (empty($waypoint->slug)) {
                $waypoint->slug = Str::slug($waypoint->name);
            }
        });

        static::updating(function (TourWaypoint $waypoint) {
            if ($waypoint->isDirty('name') && empty($waypoint->slug)) {
                $waypoint->slug = Str::slug($waypoint->name);
            }
        });
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'linked_room_type_id');
    }

    public function hotspots(): HasMany
    {
        return $this->hasMany(TourHotspot::class, 'waypoint_id')->orderBy('sort_order');
    }

    public function activeHotspots(): HasMany
    {
        return $this->hasMany(TourHotspot::class, 'waypoint_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function getPanoramaUrl(): string
    {
        return asset('storage/'.$this->panorama_image);
    }

    public function getThumbnailUrl(): ?string
    {
        if (!$this->thumbnail_image) {
            return null;
        }
        return asset('storage/'.$this->thumbnail_image);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'entrance' => 'Entrance',
            'lobby' => 'Lobby',
            'hallway' => 'Hallway',
            'room-door' => 'Room Door',
            'room-interior' => 'Room Interior',
            'amenity' => 'Amenity',
            'common-area' => 'Common Area',
            default => ucfirst(str_replace('-', ' ', $this->type)),
        };
    }

    public function isRoomRelated(): bool
    {
        return in_array($this->type, ['room-door', 'room-interior']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position_order')->orderBy('id');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
