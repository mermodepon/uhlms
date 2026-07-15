<?php

namespace App\Support;

use InvalidArgumentException;

class CanonicalAppUrl
{
    public static function fromRelative(string $relativeUrl): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException('APP_URL must be a valid absolute HTTP or HTTPS URL.');
        }

        return $baseUrl.'/'.ltrim($relativeUrl, '/');
    }
}
