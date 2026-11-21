<?php

namespace App\Listeners;

use App\Notifications\BellNotification;
use App\Notifications\SecurityMailNotification;
use App\Notifications\StaffAlerts;
use App\Notifications\StaffCategories;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\PluginsSecurity\Events\SeriousFindingFlagged;

/**
 * Tells the staff who moderate plugins that a scan flagged a serious finding, when staff alerts go to
 * individuals. The security package mails the shared support address on its own; the site's mail class
 * stands that down in individual mode (`SecurityMailNotification::via()`), and this sends the same
 * mail to the moderators who want it, with a bell entry. In team mode the package's mail is the only one.
 */
class SendSecurityFlag
{
    public function __construct(private readonly StaffAlerts $staffAlerts) {}

    public function flagged(SeriousFindingFlagged $event): void
    {
        if (! (bool) config('filagora-plugins-security.mail.enabled', true) || ! $this->staffAlerts->toIndividuals()) {
            return;
        }

        $release = $event->release;
        $plugin = $release->plugin;
        $severity = $event->scan->severity()->label();
        $counts = collect($event->scan->finding_counts ?? [])
            ->filter()
            ->map(static fn (int $count, string $level): string => "{$level}: {$count}")
            ->implode(', ');

        $this->staffAlerts->send(
            StaffCategories::PLUGIN_SECURITY,
            fn () => new SecurityMailNotification(
                'finding_flagged',
                $release,
                ['plugin' => $plugin->name, 'version' => $release->displayVersion(), 'severity' => $severity],
                [
                    __('filagora-plugins-security::notifications.finding_flagged.counts', ['counts' => $counts !== '' ? $counts : '-']),
                    $release->status === PluginStatus::QUARANTINED
                        ? __('filagora-plugins-security::notifications.finding_flagged.held')
                        : __('filagora-plugins-security::notifications.finding_flagged.public'),
                ],
                forStaff: true,
                scan: $event->scan,
            ),
            [],
            fn () => new BellNotification(
                __('notifications.staff.plugin_security.title', ['plugin' => $plugin->name, 'version' => $release->displayVersion()]),
                __('notifications.staff.plugin_security.body', ['severity' => $severity]),
                route('plugins.show', $plugin),
                StaffCategories::PLUGIN_SECURITY,
            ),
        );
    }
}
