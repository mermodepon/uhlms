# Virtual Tour Feature - Setup & Usage Guide

## 🎯 Overview

The Virtual Tour feature provides an immersive, guided experience of the University Homestay establishment. Guests can:

- Navigate through 360° panoramic views of rooms, hallways, and amenities
- View real-time room availability and details when reaching room doors
- Make reservation requests directly from within the tour
- Bookmark favorite locations for easy reference
- Use keyboard navigation (Arrow keys, ESC)

---

## 📋 Setup Instructions

### 1. Start XAMPP

Ensure MySQL and Apache are running:
```bash
# Open XAMPP Control Panel
# Start MySQL
# Start Apache
```

### 2. Run Migrations

```bash
cd D:\xampp\htdocs\MIS\uhlms
php artisan migrate
```

This will create:
- `tour_waypoints` - Tour navigation points
- `tour_hotspots` - Interactive elements within panoramas

### 3. Seed Sample Data (Optional)

```bash
php artisan db:seed --class=VirtualTourSeeder
```

This creates sample waypoints and hotspots for testing.

### 4. Upload Panorama Images

You need to upload 360° equirectangular panorama images:

**Directory Structure:**
```
storage/app/public/virtual-tour/
├── panoramas/          # 360° images (JPEG/PNG, max 10MB each)
│   ├── entrance.jpg
│   ├── lobby.jpg
│   ├── hallway-1f.jpg
│   ├── dorm-door.jpg
│   ├── dorm-interior.jpg
│   ├── private-door.jpg
│   ├── private-interior.jpg
│   └── lounge.jpg
└── thumbnails/         # Small preview images for mini-map (optional)
    ├── entrance-thumb.jpg
    ├── lobby-thumb.jpg
    └── ...
```

**Upload via Storage Link:**
```bash
php artisan storage:link
```

**How to Create Panorama Images:**
- Use a 360° camera (Ricoh Theta, Insta360, etc.)
- Hire a professional photographer
- Use panorama stitching software from multiple photos

### 5. Build Frontend Assets

```bash
npm run build
```

For development with hot-reload:
```bash
npm run dev
```

---

## 🎮 How to Use

### For Guests

1. **Start the Tour:**
   - Visit `/virtual-tours`
   - Click "🚀 Start Interactive Tour"

2. **Navigation:**
   - **Click hotspots** (📍 markers) in the panorama for info or navigation
   - **Use arrow buttons** at the bottom (Previous/Next)
   - **Keyboard:** ← → arrow keys to navigate
   - **Mini-map:** Click any location on the bottom-right map

3. **Room Information:**
   - When you reach a "Room Door" waypoint, a side panel automatically opens
   - Shows: Room type name, description, price, amenities, aggregate availability
   - Specific room numbers and occupancy details are hidden for security
   - Click "🏨 Request Reservation" to book from within the tour

4. **Bookmarks:**
   - Click the bookmark hotspot at any location to save it
   - Click "🔖 Bookmarks" button (top-right) to view saved locations

5. **Exit Tour:**
   - Click "✕ Exit Tour" button (top-right)
   - Press ESC to close overlays/modals

### For Admins (Filament)

Access the admin panel at `/admin`:

1. **Manage Waypoints:**
   - Navigate to "Virtual Tour > Tour Waypoints"
   - Add/edit/delete tour stops
   - Set order, type, link to room types
   - Upload panorama images

2. **Manage Hotspots:**
   - Navigate to "Virtual Tour > Tour Hotspots"
   - Add interactive points within each waypoint
   - Set position (pitch/yaw coordinates)
   - Define actions (navigate, info, bookmark, external link)

**Finding Pitch/Yaw Coordinates:**
- Open the tour viewer
- Navigate to desired position in panorama
- Check browser console - coordinates are logged
- Or use the built-in Tour Editor in the admin panel

---

## 🔧 Customization

### Change Tour Colors

Edit `resources/views/guest/virtual-tour-viewer.blade.php`:
```css
/* Find and modify these in the <style> section */
.overlay-header {
    background: linear-gradient(135deg, #00491E 0%, #02681E 100%);
}
.btn-submit {
    background: #FFC600;
    color: #00491E;
}
```

### Add Custom Narration

In Filament admin, edit any waypoint and fill in the "Narration" field. This text auto-appears when users reach that location.

### Enable Auto-Rotate

Edit `resources/js/tour-engine.js`, line ~70:
```javascript
autoRotate: -2, // Negative = rotate left, positive = right (degrees/sec)
```

---

## 🧪 Testing Checklist

- [ ] MySQL is running
- [ ] Migrations executed successfully
- [ ] Panorama images uploaded to `storage/app/public/virtual-tour/panoramas/`
- [ ] Storage link created (`php artisan storage:link`)
- [ ] Frontend built (`npm run build`)
- [ ] Visit `/virtual-tours` - see tour banner
- [ ] Click "Start Interactive Tour" - tour loads
- [ ] Navigate using hotspots, arrow buttons, keyboard
- [ ] Reach a room door - info panel opens automatically
- [ ] Click "Request Reservation" - modal opens
- [ ] Submit reservation - success message appears
- [ ] Test bookmark functionality
- [ ] Test mini-map navigation
- [ ] Test ESC key to close overlays

---

## 📁 File Structure

```
app/
├── Http/Controllers/
│   └── TourController.php              # API & viewer
├── Models/
│   ├── TourWaypoint.php                # Waypoint model
│   └── TourHotspot.php                 # Hotspot model
├── Filament/Resources/
│   ├── TourWaypointResource.php        # Admin CRUD for waypoints
│   └── TourHotspotResource.php         # Admin CRUD for hotspots

database/
├── migrations/
│   ├── 2026_04_11_000001_create_tour_waypoints_table.php
│   └── 2026_04_11_000002_create_tour_hotspots_table.php
└── seeders/
    └── VirtualTourSeeder.php           # Sample data

resources/
├── views/guest/
│   ├── virtual-tours.blade.php         # Tour listing (modified)
│   └── virtual-tour-viewer.blade.php   # Main tour viewer
├── js/
│   ├── panorama-viewer.js              # Three.js panorama core
│   ├── device-orientation-controls.js  # Gyroscope support
│   ├── tour-engine.js                  # Guest tour viewer
│   └── tour-editor.js                  # Admin hotspot editor

routes/
└── web.php                             # Tour routes added
```

---

## 🐛 Troubleshooting

### Panorama not loading
- Check image path: `storage/app/public/virtual-tour/panoramas/filename.jpg`
- Run `php artisan storage:link`
- Check file permissions
- Verify image is equirectangular format (2:1 aspect ratio)

### Tour viewer shows black screen
- Open browser console for errors
- Ensure Pannellum is installed: `npm list pannellum`
- Rebuild assets: `npm run build`

### API returns 404
- Check routes: `php artisan route:list | grep tour`
- Ensure web.php has tour routes

### Reservation submission fails
- Check CSRF token in meta tag
- Verify all required fields are sent
- Check Laravel logs: `storage/logs/laravel.log`

---

## 🚀 Next Steps (Future Enhancements)

- [ ] Add ambient audio support
- [ ] Day/night mode toggle
- [ ] Multi-language narration
- [ ] Analytics tracking (most visited rooms, drop-off points)
- [ ] Social sharing (share room with friends)
- [ ] Mobile app integration
- [ ] VR headset support (WebXR)
- [ ] Floor plan mini-map with real-time position
- [ ] Animated guide arrow for first-time users

---

## 📞 Support

For questions or issues, check the Laravel logs or contact the development team.

**Enjoy the Virtual Tour! 🎉**
