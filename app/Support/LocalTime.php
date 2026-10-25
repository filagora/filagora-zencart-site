<?php

namespace App\Support;

use App\Models\User;
use App\TimeFormat;
use Carbon\CarbonInterface;

/**
 * Every timestamp displayed anywhere in the app used to call ->format()
 * directly on the raw (UTC-stored) Carbon instance, so a user's saved
 * `timezone` preference was never actually applied to anything — see the
 * live bug report at zen-cart.com/threads/207260. This is the single place
 * that now applies both the viewing user's timezone and their `time_format`
 * (12h/24h) preference; call it instead of ->format() directly.
 */
class LocalTime
{
    public static function format(?CarbonInterface $dateTime, string $style = 'datetime', ?User $user = null): string
    {
        if ($dateTime === null) {
            return '';
        }

        $user ??= auth()->user();
        $local = self::inUserTimezone($dateTime, $user);

        return match ($style) {
            'date' => $local->format('j M Y'),
            'month' => $local->format('M Y'),
            'precise' => $local->format('Y-m-d H:i:s'),
            default => $local->format(self::is24Hour($user) ? 'j M Y, H:i' : 'j M Y, g:i A'),
        };
    }

    private static function inUserTimezone(CarbonInterface $dateTime, ?User $user): CarbonInterface
    {
        $timezone = trim((string) ($user?->timezone ?? '')) ?: (string) config('app.timezone');

        try {
            return $dateTime->clone()->setTimezone($timezone);
        } catch (\Throwable) {
            // A bad stored timezone string (this table has had legacy-import
            // data-quality problems before) shouldn't take down every
            // timestamp on the page - fall back to the app default instead.
            return $dateTime->clone()->setTimezone((string) config('app.timezone'));
        }
    }

    private static function is24Hour(?User $user): bool
    {
        return ($user?->time_format ?? TimeFormat::TWELVE_HOUR) === TimeFormat::TWENTY_FOUR_HOUR;
    }
}
