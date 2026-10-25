<?php

namespace App\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Ban a user, translating a duration preset into `banned_until`. The one
 * place ban dates are computed — used by the user editor and the report
 * resolver so both agree on what "30 days" means.
 */
class BanUserAction
{
    /**
     * Preset key => interval spec. "permanent" and "custom" are handled
     * separately.
     *
     * @var array<string, string>
     */
    public const DURATIONS = [
        '1d' => '1 day',
        '7d' => '7 days',
        '30d' => '30 days',
        '90d' => '90 days',
        '1y' => '1 year',
    ];

    public function execute(User $user, ?string $reason, string $duration, ?CarbonInterface $customUntil = null): void
    {
        $reason = trim((string) $reason);

        $user->forceFill([
            'is_banned' => true,
            'banned_at' => $user->banned_at ?? CarbonImmutable::now(),
            'banned_until' => $this->resolveUntil($duration, $customUntil),
            'ban_reason' => $reason !== '' ? $reason : $user->ban_reason,
        ])->save();
    }

    public function resolveUntil(string $duration, ?CarbonInterface $customUntil): ?CarbonInterface
    {
        if ($duration === 'permanent') {
            return null;
        }

        if ($duration === 'custom') {
            return $customUntil ? CarbonImmutable::instance($customUntil) : null;
        }

        $spec = self::DURATIONS[$duration] ?? self::DURATIONS['30d'];

        return CarbonImmutable::now()->add($spec);
    }

    /**
     * Best-fit preset key for an existing ban, for pre-selecting the editor
     * control. Returns 'permanent' for an indefinite ban and 'custom' when
     * the end date is not one of the presets (within a minute's tolerance).
     */
    public function presetFor(?CarbonInterface $bannedUntil): string
    {
        if ($bannedUntil === null) {
            return 'permanent';
        }

        foreach (self::DURATIONS as $key => $spec) {
            if (CarbonImmutable::now()->add($spec)->diffInMinutes($bannedUntil, absolute: true) <= 1) {
                return $key;
            }
        }

        return 'custom';
    }
}
