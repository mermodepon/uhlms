<?php

namespace App\Support;

use App\Models\Setting;

class GuestSiteSettings
{
    public const ARRAY_KEYS = [
        'guest_hero_bullets',
        'guest_reservation_steps',
        'guest_faq_items',
    ];

    public const BOOLEAN_KEYS = [
        'guest_announcement_enabled',
        'guest_maintenance_enabled',
        'guest_show_virtual_tour_cta',
        'guest_show_quick_availability',
        'guest_show_stay_guide',
        'guest_show_booking_policy',
        'guest_show_faq',
        'guest_high_contrast',
        'guest_large_text',
        'guest_hero_background_enabled',
    ];

    public static function defaults(): array
    {
        return [
            'guest_site_title' => 'CMU Homestay',
            'guest_institution_name' => 'Central Mindanao University',
            'guest_brand_name' => 'University Homestay',
            'guest_logo' => null,
            'guest_primary_accent_color' => '#FFC600',
            'guest_theme_font' => 'sans',
            'guest_announcement_enabled' => false,
            'guest_announcement_text' => null,
            'guest_maintenance_enabled' => false,
            'guest_maintenance_message' => null,
            'guest_high_contrast' => false,
            'guest_large_text' => false,
            'guest_hero_badge' => '360° Virtual Tour Available',
            'guest_hero_headline' => 'University Homestay',
            'guest_hero_message' => 'Comfortable and affordable lodging for visiting scholars, faculty, students, and guests of Central Mindanao University.',
            'guest_hero_primary_cta_label' => 'Start Virtual Tour',
            'guest_hero_secondary_cta_label' => 'Browse Rooms',
            'guest_hero_background_enabled' => false,
            'guest_hero_background_image' => null,
            'guest_hero_background_opacity' => 80,
            'guest_show_virtual_tour_cta' => true,
            'guest_show_quick_availability' => true,
            'guest_show_stay_guide' => true,
            'guest_home_rooms_heading' => 'Our Accommodations',
            'guest_home_rooms_intro' => 'Choose from a variety of room types designed to meet your needs and budget during your stay at CMU.',
            'guest_stay_guide_heading' => 'Stay Inclusions & Optional Add-ons',
            'guest_stay_guide_intro' => 'A quick overview of what guests commonly enjoy during their stay and the extra services that may be arranged when needed.',
            'guest_booking_policy' => null,
            'guest_show_booking_policy' => false,
            'guest_show_faq' => false,
            'guest_reservation_steps_heading' => 'How to Reserve',
            'guest_reservation_steps_intro' => 'Simple steps to request a stay at CMU University Homestay',
            'guest_footer_address' => "Central Mindanao University\nMusuan, Maramag, Bukidnon\nPhilippines",
            'guest_footer_phone' => null,
            'guest_footer_email' => null,
            'guest_footer_copyright_name' => 'CMU University Homestay Lodging Management System',
            'guest_footer_rooms_label' => 'Room Catalog',
            'guest_footer_tour_label' => 'Virtual Tour',
            'guest_footer_reserve_label' => 'Request a Reservation',
            'guest_footer_track_label' => 'Track Reservation',
            'guest_nav_home_label' => 'Home',
            'guest_nav_rooms_label' => 'Rooms',
            'guest_nav_tour_label' => 'Virtual Tour',
            'guest_nav_reserve_label' => 'Request Stay',
            'guest_nav_track_label' => 'Track Status',
            'guest_hero_bullets' => [
                'Navigate freely between rooms and common areas',
                'View room details and real-time availability',
                'Request a reservation directly from within the tour',
            ],
            'guest_reservation_steps' => [
                ['title' => 'Browse Rooms', 'description' => 'Explore our room types and take virtual tours'],
                ['title' => 'Submit Request', 'description' => 'Send your reservation request with your stay details'],
                ['title' => 'Review & Approval', 'description' => 'Staff checks availability, policy, and room options'],
                ['title' => 'Check In', 'description' => 'Room assigned and ready for your arrival'],
            ],
            'guest_faq_items' => [],
        ];
    }

    public static function all(): array
    {
        $values = [];

        foreach (static::defaults() as $key => $default) {
            $values[$key] = static::get($key);
        }

        return $values;
    }

    public static function get(string $key): mixed
    {
        $default = static::defaults()[$key] ?? null;
        $value = Setting::get($key, $default);

        if (in_array($key, static::BOOLEAN_KEYS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (in_array($key, static::ARRAY_KEYS, true)) {
            if (is_array($value)) {
                return $value;
            }

            $decoded = json_decode((string) $value, true);

            return is_array($decoded) ? $decoded : $default;
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        if (in_array($key, static::BOOLEAN_KEYS, true)) {
            Setting::set($key, $value ? '1' : '0');

            return;
        }

        if (in_array($key, static::ARRAY_KEYS, true)) {
            Setting::set($key, json_encode($value ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        Setting::set($key, $value);
    }

    public static function logoUrl(): string
    {
        $logo = static::get('guest_logo');

        return $logo ? (MediaUrl::url($logo) ?? '/images/uh_logo.jpg') : '/images/uh_logo.jpg';
    }

    public static function heroBackgroundUrl(): ?string
    {
        if (! static::get('guest_hero_background_enabled')) {
            return null;
        }

        $image = static::get('guest_hero_background_image');

        return $image ? MediaUrl::url($image) : null;
    }

    public static function heroBackgroundOverlayOpacity(): float
    {
        $opacity = (float) static::get('guest_hero_background_opacity');

        return max(0.45, min(0.85, $opacity / 100));
    }
}
