<?php

namespace App\Notifications;

use Filagora\Notifications\Contracts\StaffAudience;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\NotificationCategories;
use Filagora\Notifications\Services\Preferences;

/**
 * Who each staff alert would go to, for the dashboard page that lets an administrator check the
 * list before switching alerts to individuals. It asks the same questions the sender does (who holds
 * the area, whether the person wants each channel, whether their address is suppressed), so the page
 * cannot disagree with what is sent.
 */
class StaffAlertRecipients
{
    public function __construct(
        private readonly NotificationCategories $categories,
        private readonly StaffAudience $audience,
        private readonly Preferences $preferences,
    ) {}

    /**
     * @return list<array{id: string, label: string, access: string, channels: list<string>, people: list<array{name: string, email: string, wants: array<string, bool>}>, told: int}>
     */
    public function report(): array
    {
        $report = [];

        foreach (StaffCategories::definitions() as $id => $definition) {
            $category = $this->categories->get($id);
            $people = [];

            foreach ($this->audience->holding($definition['access']) as $person) {
                if (! $this->preferences->isOfferedTo($person, $category)) {
                    continue;
                }

                $wants = [];

                foreach ($category->channels as $channel) {
                    $wants[$channel->value] = $this->preferences->canDeliver($person, $category, $channel);
                }

                $people[] = [
                    'name' => (string) ($person->username ?? $person->name ?? $person->email),
                    'email' => (string) $person->email,
                    'wants' => $wants,
                ];
            }

            usort($people, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            $report[] = [
                'id' => $id,
                'label' => $category->label(),
                'access' => $definition['access'],
                'channels' => array_map(fn (NotificationChannel $channel): string => $channel->value, $category->channels),
                'people' => $people,
                'told' => count(array_filter($people, fn (array $person): bool => in_array(true, $person['wants'], true))),
            ];
        }

        return $report;
    }
}
