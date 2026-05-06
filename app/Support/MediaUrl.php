<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    public static function disk(): string
    {
        return (string) config('media.disk', 'public');
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (static::disk() === 'public') {
            return '/storage/'.ltrim($path, '/');
        }

        return Storage::disk(static::disk())->url($path);
    }

    public static function absoluteUrl(?string $path): ?string
    {
        $url = static::url($path);

        if (blank($url)) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return $url;
        }

        return url($url);
    }
}
