<?php

namespace Tests\Unit\Support;

use App\Models\Setting;
use App\Support\GuestSiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_defaults_when_settings_are_missing(): void
    {
        $settings = GuestSiteSettings::all();

        $this->assertSame('CMU Homestay', $settings['guest_site_title']);
        $this->assertSame('University Homestay', $settings['guest_brand_name']);
        $this->assertTrue($settings['guest_show_virtual_tour_cta']);
        $this->assertFalse($settings['guest_hero_background_enabled']);
        $this->assertSame(0.8, GuestSiteSettings::heroBackgroundOverlayOpacity());
        $this->assertCount(3, $settings['guest_hero_bullets']);
    }

    public function test_casts_boolean_and_json_settings(): void
    {
        Setting::set('guest_show_faq', '1');
        Setting::set('guest_faq_items', json_encode([
            ['question' => 'Can I book online?', 'answer' => 'Yes.'],
        ]));

        $settings = GuestSiteSettings::all();

        $this->assertTrue($settings['guest_show_faq']);
        $this->assertSame('Can I book online?', $settings['guest_faq_items'][0]['question']);
    }

    public function test_set_stores_arrays_as_json_and_booleans_as_flags(): void
    {
        GuestSiteSettings::set('guest_announcement_enabled', true);
        GuestSiteSettings::set('guest_hero_bullets', ['One', 'Two']);

        $this->assertDatabaseHas('settings', [
            'key' => 'guest_announcement_enabled',
            'value' => '1',
        ]);

        $this->assertSame(['One', 'Two'], GuestSiteSettings::get('guest_hero_bullets'));
    }

    public function test_hero_background_url_requires_enabled_image(): void
    {
        GuestSiteSettings::set('guest_hero_background_image', 'site-settings/hero/lobby.jpg');

        $this->assertNull(GuestSiteSettings::heroBackgroundUrl());

        GuestSiteSettings::set('guest_hero_background_enabled', true);

        $this->assertSame('/storage/site-settings/hero/lobby.jpg', GuestSiteSettings::heroBackgroundUrl());
    }

    public function test_hero_background_overlay_opacity_is_clamped_for_readability(): void
    {
        GuestSiteSettings::set('guest_hero_background_opacity', 20);
        $this->assertSame(0.45, GuestSiteSettings::heroBackgroundOverlayOpacity());

        GuestSiteSettings::set('guest_hero_background_opacity', 95);
        $this->assertSame(0.85, GuestSiteSettings::heroBackgroundOverlayOpacity());
    }
}
