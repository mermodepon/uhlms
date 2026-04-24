# Virtual Tour Setup

## Overview

The virtual tour currently uses:

- `app/Http/Controllers/TourController.php` for guest pages and APIs
- `app/Models/TourWaypoint.php` and `app/Models/TourHotspot.php` for tour data
- `app/Filament/Resources/VirtualTourResource.php` for admin management
- `app/Filament/Resources/VirtualTourResource/Pages/ManageTourHotspots.php` for hotspot editing
- `resources/js/panorama-viewer.js`, `resources/js/tour-engine.js`, and `resources/js/tour-editor.js` for the frontend

## Required App State

Before testing the tour, make sure:

- the application is already set up with the existing database schema
- `php artisan storage:link` has been run
- frontend assets have been built with `npm run build` or are running with `npm run dev`
- waypoint panorama files and optional hotspot media exist on the configured public media disk

This guide intentionally avoids fresh-install and migration-specific setup steps.

## Media Locations

With the default local/public media setup, tour files are typically stored under:

```text
storage/app/public/virtual-tour/panoramas/
storage/app/public/virtual-tour/hotspot-media/
```

Guests access those files through the app's media URL helpers rather than hardcoded local paths.

## Admin Workflow

Use the admin panel at `/admin`:

1. Open `Virtual Tour`.
2. Create or edit a waypoint.
3. Upload the panorama image and optional thumbnail.
4. Save the waypoint.
5. Open the waypoint's `Manage Hotspots` page.
6. Place, edit, reorder, or remove hotspots.
7. Use the guest preview from the editor when needed.

The current codebase manages waypoints and hotspots through one Filament resource, not separate `TourWaypointResource` or `TourHotspotResource` classes.

## Guest Workflow

Guests can:

- browse `/virtual-tours`
- open `/tour/{slug?}`
- navigate between scenes
- view room information panels
- follow hotspot actions
- start a reservation flow from the tour when enabled by the current scene

## Frontend Entry Points

These are the active entry points in the current repo:

- `resources/js/tour-engine.js` for the guest viewer
- `resources/js/tour-editor.js` for the admin editor
- `resources/js/panorama-viewer.js` as the shared panorama wrapper

There is no active `device-orientation-controls.js` file in this codebase.

## Quick Verification Checklist

- `php artisan storage:link` succeeds or the `public/storage` symlink already exists
- `npm run build` completes successfully
- at least one waypoint exists with a valid panorama image
- `/virtual-tours` loads
- `/tour/{slug}` loads a panorama
- hotspot placement and save actions work in the admin editor

## Troubleshooting

### Panorama does not load

- Confirm the stored media path exists on the selected public media disk.
- Confirm the app can serve `storage` URLs.
- Confirm the waypoint has a valid panorama image value saved.

### Hotspot editor appears blank

- Rebuild frontend assets.
- Check browser console errors related to `tour-editor.js` or `panorama-viewer.js`.
- Confirm the waypoint has a panorama image before opening the hotspot editor.

### Guest room info is missing

- Confirm the waypoint is linked to the room or room-type data expected by the current tour flow.
- Confirm the related room and room type records still exist.
