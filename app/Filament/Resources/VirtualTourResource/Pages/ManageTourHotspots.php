<?php

namespace App\Filament\Resources\VirtualTourResource\Pages;

use App\Filament\Resources\VirtualTourResource;
use App\Models\TourHotspot;
use App\Models\TourWaypoint;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class ManageTourHotspots extends Page
{
    use WithFileUploads;

    protected static string $resource = VirtualTourResource::class;

    protected static string $view = 'filament.pages.tour-editor';

    public ?int $activeWaypointId = null;

    /** Temporary Livewire upload property */
    public $hotspotImageFile = null;

    public function mount(int|string $record): void
    {
        $waypoint = TourWaypoint::findOrFail($record);
        $this->activeWaypointId = $waypoint->id;
    }

    public function getTitle(): string|Htmlable
    {
        $wp = $this->getActiveWaypoint();
        return $wp ? "Tour Editor: {$wp->name}" : 'Tour Editor';
    }

    public function getBreadcrumbs(): array
    {
        return [
            static::$resource::getUrl() => 'Virtual Tour',
            '' => 'Tour Editor',
        ];
    }

    /**
     * Upload a hotspot image and return its public URL.
     * Called from Alpine via $wire.upload().
     */
    public function uploadHotspotImage(): string
    {
        $this->validate([
            'hotspotImageFile' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
        ]);

        $path = $this->hotspotImageFile->store('virtual-tour/hotspot-media', 'public');
        $this->hotspotImageFile = null;

        return asset('storage/' . $path);
    }

    /**
     * Switch active scene — returns hotspots JSON for the new scene.
     * NOTE: Does NOT update $activeWaypointId to avoid triggering a full
     * Livewire re-render which would destroy the PSV viewer.
     */
    public function switchScene(int $waypointId): string
    {
        return $this->getHotspotsForScene($waypointId);
    }

    /**
     * Save the current camera position as the default view for a waypoint.
     */
    public function setDefaultView(int $waypointId, float $yaw, float $pitch, int $zoom): void
    {
        $wp = TourWaypoint::findOrFail($waypointId);
        $wp->update([
            'default_yaw' => $yaw,
            'default_pitch' => $pitch,
            'default_zoom' => $zoom,
        ]);

        Notification::make()->title('Default view saved!')->success()->send();
    }

    public function setRoomInfoPosition(int $waypointId, float $yaw, float $pitch): void
    {
        $wp = TourWaypoint::findOrFail($waypointId);
        $wp->update([
            'room_info_yaw'   => $yaw,
            'room_info_pitch' => $pitch,
        ]);

        // Reflect updated values back so the editor JS can move the marker immediately
        $this->dispatch('room-info-position-updated', [
            'waypointId' => $waypointId,
            'yaw'        => $yaw,
            'pitch'      => $pitch,
        ]);

        Notification::make()->title('Room Info marker repositioned!')->success()->send();
    }

    /**
     * Get hotspots JSON for a specific waypoint.
     */
    public function getHotspotsForScene(int $waypointId): string
    {
        $wp = TourWaypoint::with('hotspots')->find($waypointId);
        if (!$wp) return '[]';

        return $wp->hotspots->map(fn(TourHotspot $h) => [
            'id' => $h->id,
            'title' => $h->title,
            'description' => $h->description ?? '',
            'media_type' => $h->media_type,
            'media_url' => $h->media_url ?? '',
            'icon' => $h->icon ?? '📍',
            'pitch' => (float) $h->pitch,
            'yaw' => (float) $h->yaw,
            'action_type' => $h->action_type,
            'action_target' => $h->action_target,
            'sort_order' => $h->sort_order,
            'is_active' => (bool) $h->is_active,
        ])->values()->toJson();
    }

    /**
     * Create a new hotspot at the given coordinates.
     */
    public function saveHotspot(
        int $waypointId,
        string $title,
        string $description,
        string $icon,
        float $pitch,
        float $yaw,
        string $actionType,
        ?string $actionTarget,
        int $sortOrder,
        bool $isActive,
        ?string $mediaType = null,
        ?string $mediaUrl = null,
    ): void {
        TourHotspot::create([
            'waypoint_id' => $waypointId,
            'title' => $title,
            'description' => $description ?: null,
            'media_type' => $mediaType ?: null,
            'media_url' => ($mediaType && $mediaUrl) ? $mediaUrl : null,
            'icon' => $icon,
            'pitch' => $pitch,
            'yaw' => $yaw,
            'action_type' => $actionType,
            'action_target' => $actionTarget,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        Notification::make()->title('Hotspot created!')->success()->send();
        $this->dispatch('hotspots-updated');
    }

    /**
     * Update an existing hotspot's properties.
     */
    public function updateHotspot(
        int $hotspotId,
        string $title,
        string $description,
        string $icon,
        float $pitch,
        float $yaw,
        string $actionType,
        ?string $actionTarget,
        int $sortOrder,
        bool $isActive,
        ?string $mediaType = null,
        ?string $mediaUrl = null,
    ): void {
        $hotspot = TourHotspot::findOrFail($hotspotId);
        $hotspot->update([
            'title' => $title,
            'description' => $description ?: null,
            'media_type' => $mediaType ?: null,
            'media_url' => ($mediaType && $mediaUrl) ? $mediaUrl : null,
            'icon' => $icon,
            'pitch' => $pitch,
            'yaw' => $yaw,
            'action_type' => $actionType,
            'action_target' => $actionTarget,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        Notification::make()->title('Hotspot updated!')->success()->send();
        $this->dispatch('hotspots-updated');
    }

    /**
     * Delete a hotspot.
     */
    public function deleteHotspot(int $hotspotId): void
    {
        TourHotspot::findOrFail($hotspotId)->delete();
        Notification::make()->title('Hotspot deleted')->success()->send();
        $this->dispatch('hotspots-updated');
    }

    /**
     * Persist a new sort order after drag-and-drop reordering (called from Alpine).
     * No hotspots-updated dispatch — the reorder is already applied client-side.
     */
    public function reorderHotspots(array $orderedIds): void
    {
        foreach ($orderedIds as $order => $id) {
            TourHotspot::where('id', (int) $id)->update(['sort_order' => $order]);
        }
    }

    // ── Computed Properties ──────────────────────────────────────

    public function getActiveWaypoint(): ?TourWaypoint
    {
        if (!$this->activeWaypointId) return null;
        return TourWaypoint::with('hotspots')->find($this->activeWaypointId);
    }

    #[Computed]
    public function waypointsJson(): string
    {
        $waypoints = TourWaypoint::withCount('hotspots')
            ->orderBy('position_order')
            ->get()
            ->map(fn(TourWaypoint $wp) => [
                'id' => $wp->id,
                'name' => $wp->name,
                'slug' => $wp->slug,
                'type' => $wp->type,
                'type_label' => $wp->getTypeLabel(),
                'panorama_url' => $wp->getPanoramaUrl(),
                'thumbnail_url' => $wp->getThumbnailUrl(),
                'position_order' => $wp->position_order,
                'default_yaw' => (float) $wp->default_yaw,
                'default_pitch' => (float) $wp->default_pitch,
                'default_zoom' => (int) $wp->default_zoom,
                'is_active' => (bool) $wp->is_active,
                'hotspots_count' => $wp->hotspots_count,
                'linked_room_type_id' => $wp->linked_room_type_id,
                'room_info_yaw'       => $wp->room_info_yaw !== null ? (float) $wp->room_info_yaw : null,
                'room_info_pitch'     => $wp->room_info_pitch !== null ? (float) $wp->room_info_pitch : null,
            ]);

        return $waypoints->toJson();
    }

    #[Computed]
    public function hotspotsJson(): string
    {
        $wp = $this->getActiveWaypoint();
        if (!$wp) return '[]';

        return $wp->hotspots->map(fn(TourHotspot $h) => [
            'id' => $h->id,
            'title' => $h->title,
            'description' => $h->description ?? '',
            'media_type' => $h->media_type,
            'media_url' => $h->media_url ?? '',
            'icon' => $h->icon ?? '📍',
            'pitch' => (float) $h->pitch,
            'yaw' => (float) $h->yaw,
            'action_type' => $h->action_type,
            'action_target' => $h->action_target,
            'sort_order' => $h->sort_order,
            'is_active' => (bool) $h->is_active,
        ])->values()->toJson();
    }
}
