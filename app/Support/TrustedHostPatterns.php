<?php

namespace App\Support;

class TrustedHostPatterns
{
    /**
     * Convert literal hostnames and IP addresses to exact regular expressions.
     *
     * @param  array<int, string>  $hosts
     * @return array<int, string>
     */
    public static function fromLiterals(array $hosts): array
    {
        return collect($hosts)
            ->map(fn (string $host): string => trim($host))
            ->filter()
            ->unique()
            ->map(fn (string $host): string => str_contains($host, ':') && ! str_starts_with($host, '[')
                ? "[{$host}]"
                : $host)
            ->map(fn (string $host): string => '^'.preg_quote($host, '#').'$')
            ->values()
            ->all();
    }
}
