<?php

namespace App\Notifications;

use Filagora\Notifications\Category;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\NotificationCategories;

/**
 * The notification categories only this site has: the news a member gets about their own plugin
 * submissions, their plugin reports and their access requests. Each offers email and the bell, and
 * each is on until the member turns it off. The packages' categories and the Zen Cart profile's
 * (plugin updates, privacy request updates) are registered before these.
 */
final class SiteCategories
{
    /** A plugin or release you submitted: received, approved, rejected, or held for a security scan. */
    public const PLUGIN_SUBMISSIONS = 'plugins.my_submissions';

    /** A report you made about a plugin: reviewed. */
    public const PLUGIN_REPORTS = 'plugins.my_reports';

    /** An access request you made: received, or its status changed. */
    public const ENTITLEMENT_REQUESTS = 'entitlements.request_updates';

    /** Registered by the Zen Cart profile, and required: a person is always told about their own privacy request. */
    public const PRIVACY_REQUESTS = 'privacy.request_updates';

    public static function register(NotificationCategories $categories): void
    {
        $emailAndBell = [NotificationChannel::EMAIL, NotificationChannel::IN_APP];

        $categories->register(new Category(self::PLUGIN_SUBMISSIONS, 'Your plugin submissions: receipts, approvals and rejections', $emailAndBell));
        $categories->register(new Category(self::PLUGIN_REPORTS, 'Updates to plugin reports you made', $emailAndBell));
        $categories->register(new Category(self::ENTITLEMENT_REQUESTS, 'Updates to your access requests', $emailAndBell));
    }
}
