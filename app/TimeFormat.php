<?php

namespace App;

enum TimeFormat: string
{
    case TWELVE_HOUR = '12h';
    case TWENTY_FOUR_HOUR = '24h';

    public function label(): string
    {
        return match ($this) {
            self::TWELVE_HOUR => '12-hour (AM/PM)',
            self::TWENTY_FOUR_HOUR => '24-hour',
        };
    }
}
