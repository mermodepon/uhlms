<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourHotspot extends Model
{
    use HasFactory;

    protected $fillable = [
        'waypoint_id',
        'title',
        'description',
        'media_type',
        'media_url',
        'icon',
        'pitch',
        'yaw',
        'action_type',
        'action_target',
        'sort_order',
        'is_active',
        'size',
    ];

    protected $casts = [
        'pitch' => 'decimal:4',
        'yaw' => 'decimal:4',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'size' => 'integer',
    ];

    public function waypoint(): BelongsTo
    {
        return $this->belongsTo(TourWaypoint::class, 'waypoint_id');
    }

    public function getActionTypeLabel(): string
    {
        return match ($this->action_type) {
            'info'          => 'Information',
            'navigate'      => 'Navigate to Location',
            'bookmark'      => 'Bookmark Location',
            'external-link' => 'External Link',
            default         => ucfirst(str_replace('-', ' ', $this->action_type)),
        };
    }

    public function isNavigationHotspot(): bool
    {
        return $this->action_type === 'navigate';
    }

    public function getTargetWaypoint(): ?TourWaypoint
    {
        if (!$this->isNavigationHotspot() || !$this->action_target) {
            return null;
        }
        return TourWaypoint::where('slug', $this->action_target)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeByActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }
}
