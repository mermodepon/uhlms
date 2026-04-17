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
        'linked_room_id',
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

        // Automatically set linked_room_type_id from linked_room if not set
        static::saving(function (TourWaypoint $waypoint) {
            if ($waypoint->linked_room_id && !$waypoint->linked_room_type_id) {
                $room = \App\Models\Room::find($waypoint->linked_room_id);
                if ($room) {
                    $waypoint->linked_room_type_id = $room->room_type_id;
                }
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'linked_room_id');
    }

    /**
     * Get the linked room information with priority: specific room > room type.
     * Returns Room object if linked_room_id is set, otherwise RoomType object.
     */
    public function getLinkedRoomInfo()
    {
        // Priority 1: Specific room
        if ($this->linked_room_id && $this->room) {
            return $this->room;
        }

        // Priority 2: Room type (fall back)
        if ($this->linked_room_type_id && $this->roomType) {
            return $this->roomType;
        }

        return null;
    }

    /**
     * Check if this waypoint is linked to a specific room (not just a room type).
     */
    public function hasSpecificRoom(): bool
    {
        return !is_null($this->linked_room_id);
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
