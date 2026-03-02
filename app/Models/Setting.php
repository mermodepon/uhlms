<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Cache TTL in seconds (1 hour). Settings rarely change.
     */
    private const CACHE_TTL = 3600;

    /**
     * Get a setting value with caching to avoid repeated DB queries.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting.{$key}", self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting?->value ?? $default;
        });
    }

    /**
     * Set a setting value and bust the cache.
     */
    public static function set(string $key, $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("setting.{$key}");
    }

    /**
     * Clear all setting caches (useful after bulk updates).
     */
    public static function clearCache(): void
    {
        self::pluck('key')->each(fn ($key) => Cache::forget("setting.{$key}"));
    }
}
