<?php

namespace Database\Seeders;

use App\Models\TourHotspot;
use App\Models\TourWaypoint;
use Illuminate\Database\Seeder;

class VirtualTourSeeder extends Seeder
{
    public function run(): void
    {
        // Create sample waypoints for a typical university homestay tour
        $waypoints = [
            [
                'name' => 'Main Entrance',
                'slug' => 'main-entrance',
                'type' => 'entrance',
                'panorama_image' => 'virtual-tour/panoramas/entrance.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/entrance-thumb.jpg',
                'position_order' => 1,
                'description' => 'Welcome to the University Homestay main entrance',
                'narration' => 'Welcome! This is the main entrance of the University Homestay. Let\'s begin your virtual tour.',
                'is_active' => true,
            ],
            [
                'name' => 'Lobby & Reception',
                'slug' => 'lobby-reception',
                'type' => 'lobby',
                'panorama_image' => 'virtual-tour/panoramas/lobby.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/lobby-thumb.jpg',
                'position_order' => 2,
                'linked_room_type_id' => null,
                'description' => 'Main lobby and reception area with 24/7 front desk service',
                'narration' => 'Our welcoming lobby features comfortable seating areas and a 24/7 reception desk ready to assist you.',
                'is_active' => true,
            ],
            [
                'name' => 'Main Hallway - First Floor',
                'slug' => 'hallway-first-floor',
                'type' => 'hallway',
                'panorama_image' => 'virtual-tour/panoramas/hallway-1f.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/hallway-1f-thumb.jpg',
                'position_order' => 3,
                'description' => 'First floor hallway leading to rooms',
                'narration' => 'This is the first floor hallway. Rooms are located on both sides.',
                'is_active' => true,
            ],
            [
                'name' => 'Standard Dorm Room - Door',
                'slug' => 'standard-dorm-door',
                'type' => 'room-door',
                'panorama_image' => 'virtual-tour/panoramas/dorm-door.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/dorm-door-thumb.jpg',
                'position_order' => 4,
                'description' => 'Standard dormitory room entrance',
                'narration' => 'This is a standard dormitory room. Click to see room details and availability.',
                'is_active' => true,
            ],
            [
                'name' => 'Standard Dorm Room - Interior',
                'slug' => 'standard-dorm-interior',
                'type' => 'room-interior',
                'panorama_image' => 'virtual-tour/panoramas/dorm-interior.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/dorm-interior-thumb.jpg',
                'position_order' => 5,
                'description' => 'Interior view of standard dormitory with bunk beds',
                'narration' => 'Our dorm rooms feature comfortable bunk beds with personal storage spaces.',
                'is_active' => true,
            ],
            [
                'name' => 'Private Room - Door',
                'slug' => 'private-room-door',
                'type' => 'room-door',
                'panorama_image' => 'virtual-tour/panoramas/private-door.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/private-door-thumb.jpg',
                'position_order' => 6,
                'description' => 'Private room entrance',
                'narration' => 'This is a private room offering exclusive space for your comfort.',
                'is_active' => true,
            ],
            [
                'name' => 'Private Room - Interior',
                'slug' => 'private-room-interior',
                'type' => 'room-interior',
                'panorama_image' => 'virtual-tour/panoramas/private-interior.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/private-interior-thumb.jpg',
                'position_order' => 7,
                'description' => 'Interior view of private room with full amenities',
                'narration' => 'Private rooms come with a comfortable bed, study desk, and private bathroom.',
                'is_active' => true,
            ],
            [
                'name' => 'Common Lounge & Kitchen',
                'slug' => 'common-lounge',
                'type' => 'common-area',
                'panorama_image' => 'virtual-tour/panoramas/lounge.jpg',
                'thumbnail_image' => 'virtual-tour/thumbnails/lounge-thumb.jpg',
                'position_order' => 8,
                'description' => 'Shared lounge area with kitchen facilities',
                'narration' => 'Our common lounge features a fully equipped kitchen, dining area, and comfortable seating for relaxation.',
                'is_active' => true,
            ],
        ];

        foreach ($waypoints as $data) {
            TourWaypoint::create($data);
        }

        // Create sample hotspots
        $hotspots = [
            // Entrance hotspots
            [
                'waypoint_slug' => 'main-entrance',
                'title' => 'Enter Lobby',
                'description' => 'Click to enter the lobby and reception area',
                'icon' => 'arrow-right',
                'pitch' => 0,
                'yaw' => 45,
                'action_type' => 'navigate',
                'action_target' => 'lobby-reception',
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'main-entrance',
                'title' => 'Security Camera',
                'description' => '24/7 CCTV surveillance for your safety',
                'icon' => 'video-camera',
                'pitch' => 20,
                'yaw' => -30,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 2,
            ],

            // Lobby hotspots
            [
                'waypoint_slug' => 'lobby-reception',
                'title' => 'Reception Desk',
                'description' => 'Our friendly staff is available 24/7 to assist you with check-in, check-out, and any inquiries.',
                'icon' => 'user-group',
                'pitch' => 5,
                'yaw' => 0,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'lobby-reception',
                'title' => 'Go to Hallway',
                'description' => 'Explore the rooms on the first floor',
                'icon' => 'arrow-right',
                'pitch' => 0,
                'yaw' => 90,
                'action_type' => 'navigate',
                'action_target' => 'hallway-first-floor',
                'sort_order' => 2,
            ],
            [
                'waypoint_slug' => 'lobby-reception',
                'title' => 'Free WiFi',
                'description' => 'High-speed WiFi available throughout the lobby area',
                'icon' => 'wifi',
                'pitch' => -10,
                'yaw' => -45,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 3,
            ],

            // Hallway hotspots
            [
                'waypoint_slug' => 'hallway-first-floor',
                'title' => 'Standard Dorm Room',
                'description' => 'View this dormitory room',
                'icon' => 'door-open',
                'pitch' => 0,
                'yaw' => -30,
                'action_type' => 'navigate',
                'action_target' => 'standard-dorm-door',
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'hallway-first-floor',
                'title' => 'Private Room',
                'description' => 'View this private room',
                'icon' => 'door-open',
                'pitch' => 0,
                'yaw' => 60,
                'action_type' => 'navigate',
                'action_target' => 'private-room-door',
                'sort_order' => 2,
            ],
            [
                'waypoint_slug' => 'hallway-first-floor',
                'title' => 'Common Lounge',
                'description' => 'Visit our shared lounge and kitchen area',
                'icon' => 'arrow-right',
                'pitch' => 0,
                'yaw' => 150,
                'action_type' => 'navigate',
                'action_target' => 'common-lounge',
                'sort_order' => 3,
            ],
            [
                'waypoint_slug' => 'hallway-first-floor',
                'title' => 'Fire Exit',
                'description' => 'Emergency exit located at both ends of the hallway',
                'icon' => 'exclamation-triangle',
                'pitch' => 10,
                'yaw' => -150,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 4,
            ],

            // Standard Dorm Door hotspots
            [
                'waypoint_slug' => 'standard-dorm-door',
                'title' => 'View Room Interior',
                'description' => 'Click to see inside the room',
                'icon' => 'eye',
                'pitch' => 0,
                'yaw' => 0,
                'action_type' => 'navigate',
                'action_target' => 'standard-dorm-interior',
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'standard-dorm-door',
                'title' => 'Bookmark This Room',
                'description' => 'Save this room to your bookmarks',
                'icon' => 'bookmark',
                'pitch' => -10,
                'yaw' => 45,
                'action_type' => 'bookmark',
                'action_target' => null,
                'sort_order' => 2,
            ],

            // Standard Dorm Interior hotspots
            [
                'waypoint_slug' => 'standard-dorm-interior',
                'title' => 'Bunk Beds',
                'description' => 'Comfortable bunk beds with personal reading lights and charging ports',
                'icon' => 'bed',
                'pitch' => 0,
                'yaw' => 30,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'standard-dorm-interior',
                'title' => 'Personal Locker',
                'description' => 'Each guest gets a secure personal locker',
                'icon' => 'lock-closed',
                'pitch' => -5,
                'yaw' => -60,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 2,
            ],
            [
                'waypoint_slug' => 'standard-dorm-interior',
                'title' => 'Back to Door',
                'description' => 'Return to room entrance',
                'icon' => 'arrow-left',
                'pitch' => 0,
                'yaw' => 180,
                'action_type' => 'navigate',
                'action_target' => 'standard-dorm-door',
                'sort_order' => 3,
            ],

            // Private Room Door hotspots
            [
                'waypoint_slug' => 'private-room-door',
                'title' => 'View Room Interior',
                'description' => 'Click to see inside the room',
                'icon' => 'eye',
                'pitch' => 0,
                'yaw' => 0,
                'action_type' => 'navigate',
                'action_target' => 'private-room-interior',
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'private-room-door',
                'title' => 'Bookmark This Room',
                'description' => 'Save this room to your bookmarks',
                'icon' => 'bookmark',
                'pitch' => -10,
                'yaw' => 45,
                'action_type' => 'bookmark',
                'action_target' => null,
                'sort_order' => 2,
            ],

            // Private Room Interior hotspots
            [
                'waypoint_slug' => 'private-room-interior',
                'title' => 'Queen Bed',
                'description' => 'Spacious room with a comfortable queen-size bed',
                'icon' => 'bed',
                'pitch' => 0,
                'yaw' => 0,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'private-room-interior',
                'title' => 'Study Desk',
                'description' => 'Dedicated study area with good lighting',
                'icon' => 'desktop-computer',
                'pitch' => -5,
                'yaw' => 90,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 2,
            ],
            [
                'waypoint_slug' => 'private-room-interior',
                'title' => 'Private Bathroom',
                'description' => 'En-suite bathroom with hot shower',
                'icon' => 'shower',
                'pitch' => -10,
                'yaw' => -90,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 3,
            ],

            // Common Lounge hotspots
            [
                'waypoint_slug' => 'common-lounge',
                'title' => 'Kitchen Area',
                'description' => 'Fully equipped kitchen with refrigerator, microwave, and cooking facilities',
                'icon' => 'cookie',
                'pitch' => 0,
                'yaw' => -45,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 1,
            ],
            [
                'waypoint_slug' => 'common-lounge',
                'title' => 'Dining Area',
                'description' => 'Comfortable dining space for meals and gatherings',
                'icon' => 'table',
                'pitch' => -5,
                'yaw' => 45,
                'action_type' => 'info',
                'action_target' => null,
                'sort_order' => 2,
            ],
            [
                'waypoint_slug' => 'common-lounge',
                'title' => 'Back to Hallway',
                'description' => 'Return to the hallway',
                'icon' => 'arrow-left',
                'pitch' => 0,
                'yaw' => 180,
                'action_type' => 'navigate',
                'action_target' => 'hallway-first-floor',
                'sort_order' => 3,
            ],
        ];

        foreach ($hotspots as $data) {
            $waypoint = TourWaypoint::where('slug', $data['waypoint_slug'])->first();
            if ($waypoint) {
                TourHotspot::create([
                    'waypoint_id' => $waypoint->id,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'icon' => $data['icon'],
                    'pitch' => $data['pitch'],
                    'yaw' => $data['yaw'],
                    'action_type' => $data['action_type'],
                    'action_target' => $data['action_target'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Virtual tour waypoints and hotspots seeded successfully!');
        $this->command->info('Note: You need to upload actual 360° panorama images to:');
        $this->command->info('storage/app/public/virtual-tour/panoramas/');
        $this->command->info('storage/app/public/virtual-tour/thumbnails/');
    }
}
