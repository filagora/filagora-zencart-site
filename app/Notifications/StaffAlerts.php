<?php

namespace App\Notifications;

use Closure;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Notifications\Services\Recipients;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Sends an alert about something staff handle (a plugin to moderate, a privacy request, a failed
 * backup). With the `staff_alerts_to_individuals` setting on, it goes to the individual staff who
 * hold the area's entitlement and want it, on the channels they chose, so a moderator who opts out
 * is not told. With it off (the default, so a deploy changes nothing) it goes to the shared team
 * address, as it always has.
 */
class StaffAlerts
{
    public const SETTING = 'staff_alerts_to_individuals';

    public function __construct(
        private readonly Recipients $recipients,
        private readonly SettingsRegistry $settings,
    ) {}

    public function toIndividuals(): bool
    {
        return $this->settings->boolean(self::SETTING, false);
    }

    /**
     * @param  Closure(): Notification  $mail  The mail, built fresh for each send.
     * @param  list<string|null>  $teamAddresses  The shared addresses, used when alerts go to the team.
     * @param  (Closure(): BellNotification)|null  $bell  The bell entry, for categories that offer one.
     * @param  bool  $guaranteed  Falls back to the team address when no individual would be told, for
     *                            alerts that must not go unseen (privacy requests have legal deadlines).
     */
    public function send(string $category, Closure $mail, array $teamAddresses, ?Closure $bell = null, bool $guaranteed = false): void
    {
        $team = $this->validAddresses($teamAddresses);

        if (! $this->toIndividuals()) {
            $this->toTeam($team, $mail);

            return;
        }

        $byChannel = $this->recipients->byChannel($category);
        $emailed = $byChannel['email'] ?? [];
        $belled = $bell !== null ? ($byChannel['in_app'] ?? []) : [];

        if ($emailed !== []) {
            Notifier::send($emailed, $mail());
        }

        if ($belled !== []) {
            Notifier::send($belled, $bell());
        }

        if ($guaranteed && $emailed === [] && $belled === []) {
            Log::warning("No staff member is set to be told about [{$category}]; sending it to the team address instead.");
            $this->toTeam($team, $mail);
        }
    }

    /**
     * @param  list<string>  $team
     * @param  Closure(): Notification  $mail
     */
    private function toTeam(array $team, Closure $mail): void
    {
        foreach ($team as $address) {
            Notifier::route('mail', $address)->notify($mail());
        }
    }

    /**
     * @param  list<string|null>  $addresses
     * @return list<string>
     */
    private function validAddresses(array $addresses): array
    {
        $valid = [];

        foreach ($addresses as $address) {
            $address = trim((string) $address);

            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $valid[$address] = $address;
            }
        }

        return array_values($valid);
    }
}
