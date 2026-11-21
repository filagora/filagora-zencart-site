<?php

namespace App\Notifications;

use Filagora\Notifications\Category;
use Filagora\Notifications\Enums\CategoryKind;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\NotificationCategories;

/**
 * The alerts only staff are offered, each tied to the entitlement that makes someone responsible
 * for it. A moderator sees, and chooses about, only the areas they hold; a category with a bell
 * entry offers email and the bell, and the rest offer email only. They are on until a person turns
 * them off.
 */
final class StaffCategories
{
    public const PLUGIN_SUBMISSIONS = 'staff.plugin_submissions';

    public const PLUGIN_REPORTS = 'staff.plugin_reports';

    public const PLUGIN_SECURITY = 'staff.plugin_security';

    public const PRIVACY_REQUESTS = 'staff.privacy_requests';

    public const POST_REPORTS = 'staff.post_reports';

    public const ENTITLEMENT_REQUESTS = 'staff.entitlement_requests';

    public const BACKUP_FAILURES = 'staff.backup_failures';

    public const SEARCH_HEALTH = 'staff.search_health';

    /**
     * @return array<string, array{label: string, access: string, bell: bool}>
     */
    public static function definitions(): array
    {
        return [
            self::PLUGIN_SUBMISSIONS => ['label' => 'New plugins and plugin releases to moderate', 'access' => 'operator.plugin.moderate', 'bell' => true],
            self::PLUGIN_REPORTS => ['label' => 'Plugin reports', 'access' => 'operator.plugin.moderate', 'bell' => true],
            self::PLUGIN_SECURITY => ['label' => 'Plugin security findings', 'access' => 'operator.plugin.moderate', 'bell' => true],
            self::PRIVACY_REQUESTS => ['label' => 'New privacy requests', 'access' => 'operator.privacy.requests.update', 'bell' => true],
            self::POST_REPORTS => ['label' => 'Reported forum posts', 'access' => 'operator.forum.moderate', 'bell' => true],
            self::ENTITLEMENT_REQUESTS => ['label' => 'New access requests', 'access' => 'operator.entitlements.manage', 'bell' => true],
            self::BACKUP_FAILURES => ['label' => 'Backup failures', 'access' => 'operator.system.backups.manage', 'bell' => false],
            self::SEARCH_HEALTH => ['label' => 'Search index health problems', 'access' => 'operator.system.settings.manage', 'bell' => false],
        ];
    }

    public static function register(NotificationCategories $categories): void
    {
        foreach (self::definitions() as $id => $definition) {
            $categories->register(new Category(
                $id,
                $definition['label'],
                $definition['bell'] ? [NotificationChannel::EMAIL, NotificationChannel::IN_APP] : [NotificationChannel::EMAIL],
                kind: CategoryKind::STAFF,
                access: $definition['access'],
            ));
        }
    }
}
