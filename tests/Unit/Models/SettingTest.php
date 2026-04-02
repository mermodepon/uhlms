<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $setting = new Setting;
        $this->assertEquals(['key', 'value'], $setting->getFillable());
    }

    public function test_get_returns_value(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'My Hotel']);

        $this->assertEquals('My Hotel', Setting::get('site_name'));
    }

    public function test_get_returns_default_when_not_found(): void
    {
        $this->assertEquals('default_val', Setting::get('nonexistent', 'default_val'));
    }

    public function test_get_caches_value(): void
    {
        Setting::create(['key' => 'cached_key', 'value' => 'cached_value']);

        // First call caches
        Setting::get('cached_key');

        // Verify it's cached
        $this->assertEquals('cached_value', Cache::get('setting_cached_key'));
    }

    public function test_set_creates_setting(): void
    {
        Setting::set('new_key', 'new_value');

        $this->assertDatabaseHas('settings', [
            'key' => 'new_key',
            'value' => 'new_value',
        ]);
    }

    public function test_set_updates_existing_setting(): void
    {
        Setting::create(['key' => 'update_key', 'value' => 'old_value']);
        Setting::set('update_key', 'new_value');

        $this->assertEquals('new_value', Setting::get('update_key'));
    }

    public function test_set_flushes_cache(): void
    {
        Cache::put('setting_flush_key', 'old_cached', 3600);
        Setting::set('flush_key', 'new_value');

        // After set, the cache should have been flushed
        $this->assertNull(Cache::get('setting_flush_key'));
    }

    public function test_clear_all_caches(): void
    {
        Setting::create(['key' => 'key1', 'value' => 'val1']);
        Setting::create(['key' => 'key2', 'value' => 'val2']);

        // Populate cache
        Cache::put('setting_key1', 'val1', 3600);
        Cache::put('setting_key2', 'val2', 3600);

        Setting::clearAllCaches();

        $this->assertNull(Cache::get('setting_key1'));
        $this->assertNull(Cache::get('setting_key2'));
    }
}
