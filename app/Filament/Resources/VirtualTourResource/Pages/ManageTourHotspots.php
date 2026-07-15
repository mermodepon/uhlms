<?php

namespace App\Filament\Resources\VirtualTourResource\Pages;

use App\Filament\Resources\VirtualTourResource;
use App\Models\TourHotspot;
use App\Models\TourWaypoint;
use App\Models\User;
use App\Support\MediaUrl;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Validator;
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

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasPermission(User::VIRTUAL_TOUR_VIEW) ?? false;
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasPermission(User::VIRTUAL_TOUR_MANAGE), 403);
    }

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
        $this->authorizeManage();

        $this->validate([
            'hotspotImageFile' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
        ]);

        $path = $this->hotspotImageFile->store('virtual-tour/hotspot-media', MediaUrl::disk());
        $this->hotspotImageFile = null;

        return MediaUrl::url($path) ?? '';
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
        $this->authorizeManage();

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
        $this->authorizeManage();

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

        Notification::make()->title('View Details and Request marker repositioned!')->success()->send();
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
            'size' => (int) ($h->size ?? 3),
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
        int $size = 3,
    ): void {
        $this->authorizeManage();

        $validated = $this->validateHotspotPayload([
            'waypoint_id' => $waypointId,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'pitch' => $pitch,
            'yaw' => $yaw,
            'action_type' => $actionType,
            'action_target' => $actionTarget,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
            'size' => $size,
        ]);
        if (!$validated) {
            return;
        }

        TourHotspot::create([
            'waypoint_id' => $validated['waypoint_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'media_type' => $validated['media_type'] ?: null,
            'media_url' => ($validated['media_type'] && $validated['media_url']) ? $validated['media_url'] : null,
            'icon' => $validated['icon'],
            'pitch' => $validated['pitch'],
            'yaw' => $validated['yaw'],
            'action_type' => $validated['action_type'],
            'action_target' => $validated['action_target'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
            'size' => $validated['size'],
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
        int $size = 3,
    ): void {
        $this->authorizeManage();

        $hotspot = TourHotspot::findOrFail($hotspotId);
        $validated = $this->validateHotspotPayload([
            'waypoint_id' => $hotspot->waypoint_id,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'pitch' => $pitch,
            'yaw' => $yaw,
            'action_type' => $actionType,
            'action_target' => $actionTarget,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
            'size' => $size,
        ]);
        if (!$validated) {
            return;
        }

        $hotspot->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'media_type' => $validated['media_type'] ?: null,
            'media_url' => ($validated['media_type'] && $validated['media_url']) ? $validated['media_url'] : null,
            'icon' => $validated['icon'],
            'pitch' => $validated['pitch'],
            'yaw' => $validated['yaw'],
            'action_type' => $validated['action_type'],
            'action_target' => $validated['action_target'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
            'size' => $validated['size'],
        ]);

        Notification::make()->title('Hotspot updated!')->success()->send();
        $this->dispatch('hotspots-updated');
    }

    /**
     * Delete a hotspot.
     */
    public function deleteHotspot(int $hotspotId): void
    {
        $this->authorizeManage();

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
        $this->authorizeManage();

        foreach ($orderedIds as $order => $id) {
            TourHotspot::where('id', (int) $id)->update(['sort_order' => $order]);
        }
    }

    /**
     * Persist the scene sequence used by the guest tour and editor sidebar.
     */
    public function reorderScenes(array $orderedIds): void
    {
        $this->authorizeManage();

        $ids = collect($orderedIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $existingIds = TourWaypoint::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($ids->count() !== $existingIds->count()) {
            Notification::make()
                ->title('Scene order was not saved')
                ->body('Refresh the editor and try reordering again.')
                ->danger()
                ->send();

            return;
        }

        foreach ($ids as $order => $id) {
            TourWaypoint::whereKey($id)->update(['position_order' => $order]);
        }
    }

    protected function validateHotspotPayload(array $payload): ?array
    {
        $payload['title'] = trim((string) ($payload['title'] ?? ''));
        $payload['description'] = trim((string) ($payload['description'] ?? ''));
        $payload['icon'] = trim((string) ($payload['icon'] ?? ''));
        $payload['action_target'] = $this->normalizeNullableString($payload['action_target'] ?? null);
        $payload['media_type'] = $this->normalizeNullableString($payload['media_type'] ?? null);
        $payload['media_url'] = $this->normalizeNullableString($payload['media_url'] ?? null);

        if (in_array($payload['action_type'] ?? null, ['previous-scene', 'info', 'bookmark'], true)) {
            $payload['action_target'] = null;
        }

        $validator = Validator::make($payload, [
            'waypoint_id' => ['required', 'integer', 'exists:tour_waypoints,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['required', 'string', 'max:100'],
            'pitch' => ['required', 'numeric', 'between:-90,90'],
            'yaw' => ['required', 'numeric', 'between:-360,360'],
            'action_type' => ['required', 'in:navigate,previous-scene,info,bookmark,external-link,audio,video'],
            'action_target' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'media_type' => ['nullable', 'in:image,gallery,video'],
            'media_url' => ['nullable', 'string', 'max:12000'],
            'size' => ['required', 'integer', 'between:1,5'],
        ]);

        $validator->after(function ($validator) use ($payload) {
            $actionType = $payload['action_type'] ?? null;
            $actionTarget = $payload['action_target'] ?? null;
            $mediaType = $payload['media_type'] ?? null;
            $mediaUrl = $payload['media_url'] ?? null;

            if ($actionType === 'navigate') {
                if (!$actionTarget) {
                    $validator->errors()->add('action_target', 'Choose a target scene.');
                } elseif (!TourWaypoint::where('slug', $actionTarget)->exists()) {
                    $validator->errors()->add('action_target', 'The selected target scene does not exist.');
                }
            }

            if (in_array($actionType, ['external-link', 'video'], true) && !$this->isValidHttpUrl($actionTarget)) {
                $validator->errors()->add('action_target', 'Enter a valid http or https URL.');
            }

            if ($mediaType === 'video' && $mediaUrl && !$this->isValidYouTubeUrl($mediaUrl)) {
                $validator->errors()->add('media_url', 'Enter a valid YouTube video, Shorts, or youtu.be link.');
            }

            if (in_array($mediaType, ['image', 'gallery'], true)) {
                $urls = preg_split('/\r\n|\r|\n/', (string) $mediaUrl) ?: [];
                $urls = array_values(array_filter(array_map('trim', $urls)));

                if (empty($urls)) {
                    $validator->errors()->add('media_url', 'Upload at least one image or choose no media.');
                    return;
                }

                foreach ($urls as $url) {
                    if (!$this->isValidMediaUrl($url)) {
                        $validator->errors()->add('media_url', 'Image URLs must use http, https, or a local /storage path.');
                        break;
                    }
                }
            }
        });

        if ($validator->fails()) {
            Notification::make()
                ->title('Invalid hotspot data')
                ->body($validator->errors()->first())
                ->danger()
                ->send();

            return null;
        }

        return $validator->validated();
    }

    protected function normalizeNullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    protected function isValidHttpUrl(?string $value): bool
    {
        if (!$value) {
            return false;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    protected function isValidYouTubeUrl(?string $value): bool
    {
        if (!$this->isValidHttpUrl($value)) {
            return false;
        }

        $parts = parse_url($value);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);
        parse_str((string) ($parts['query'] ?? ''), $query);

        $id = '';
        if ($host === 'youtu.be') {
            $id = trim((string) ($parts['path'] ?? ''), '/');
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
            $id = (string) ($query['v'] ?? '');

            if ($id === '' && str_starts_with((string) ($parts['path'] ?? ''), '/shorts/')) {
                $segments = array_values(array_filter(explode('/', (string) $parts['path'])));
                $id = (string) ($segments[1] ?? '');
            }

            if ($id === '' && str_starts_with((string) ($parts['path'] ?? ''), '/embed/')) {
                $segments = array_values(array_filter(explode('/', (string) $parts['path'])));
                $id = (string) ($segments[1] ?? '');
            }
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]{11}$/', $id);
    }

    protected function isValidMediaUrl(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        if ($this->isValidHttpUrl($value)) {
            return true;
        }

        return (bool) preg_match('#^/(?!/)[A-Za-z0-9/_\-.%]+$#', $value);
    }

    // ── Computed Properties ──────────────────────────────────────

    public function getActiveWaypoint(): ?TourWaypoint
    {
        if (!$this->activeWaypointId) return null;
        return TourWaypoint::with('hotspots')->find($this->activeWaypointId);
    }

    #[Computed]
    public function waypointsData(): array
    {
        $waypoints = TourWaypoint::withCount('hotspots')
            ->orderBy('position_order')
            ->orderBy('id')
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

        return $waypoints->values()->all();
    }

    #[Computed]
    public function hotspotsData(): array
    {
        $wp = $this->getActiveWaypoint();
        if (!$wp) return [];

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
            'size' => (int) ($h->size ?? 3),
        ])->values()->all();
    }
}
