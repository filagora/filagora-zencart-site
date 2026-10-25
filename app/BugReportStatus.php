<?php

namespace App;

enum BugReportStatus: string
{
    case REPORTED = 'reported';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case FEATURE_REQUEST = 'feature_request';
    case WONT_FIX = 'wont_fix';

    public function label(): string
    {
        return match ($this) {
            self::REPORTED => 'Reported',
            self::CONFIRMED => 'Confirmed',
            self::COMPLETED => 'Completed',
            self::FEATURE_REQUEST => 'Feature Request',
            self::WONT_FIX => "Won't Fix",
        };
    }

    /**
     * Terminal statuses close out a bug report: they're excluded from the
     * default "active" views (e.g. BugReportsTable's Active filter) and
     * don't clear completed_at back to null on further updates.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FEATURE_REQUEST, self::WONT_FIX => true,
            self::REPORTED, self::CONFIRMED => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function terminalValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isTerminal())
        ));
    }
}
