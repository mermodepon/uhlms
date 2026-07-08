<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class GuestDatePolicy
{
    public static function defaults(?string $checkIn = null, ?string $checkOut = null, bool $allowPastCheckIn = false): array
    {
        $today = self::today();
        $defaultCheckIn = self::normalize($checkIn) ?? $today;

        if (! $allowPastCheckIn && $defaultCheckIn < $today) {
            $defaultCheckIn = $today;
        }

        $minCheckOut = self::addDays($defaultCheckIn, 1);
        $defaultCheckOut = self::normalize($checkOut) ?? $minCheckOut;

        if ($defaultCheckOut <= $defaultCheckIn) {
            $defaultCheckOut = $minCheckOut;
        }

        return [
            'check_in' => $defaultCheckIn,
            'check_out' => $defaultCheckOut,
            'min_check_in' => $today,
            'min_check_out' => $minCheckOut,
        ];
    }

    public static function today(): string
    {
        return CarbonImmutable::today()->toDateString();
    }

    public static function addDays(string $date, int $days): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date)->addDays($days)->toDateString();
    }

    private static function normalize(?string $date): ?string
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return $date;
    }
}
