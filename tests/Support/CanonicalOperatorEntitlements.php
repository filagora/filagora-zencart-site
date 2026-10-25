<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The operator entitlements each staff role holds, for tests that need a user with a role's access.
 */
final class CanonicalOperatorEntitlements
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'forum_moderator' => [
            'operator.dashboard.access',
            'operator.queue_monitors.view',
            'operator.forum.moderate',
            'operator.forum.view_unapproved',
            'operator.forum.bypass_approval',
            'operator.plugin.moderate',
            'operator.newsletter.manage',
            'operator.cms.manage',
            'operator.site_notifications.manage',
            'operator.users.view',
            'operator.users.update',
            'operator.privacy.requests.view',
            'operator.privacy.requests.update',
            'operator.entitlements.view',
        ],
        'super_admin' => [
            'operator.dashboard.access',
            'operator.queue_monitors.view',
            'operator.forum.moderate',
            'operator.forum.view_unapproved',
            'operator.forum.bypass_approval',
            'operator.plugin.moderate',
            'operator.newsletter.manage',
            'operator.cms.manage',
            'operator.site_notifications.manage',
            'operator.users.view',
            'operator.users.update',
            'operator.privacy.requests.view',
            'operator.privacy.requests.update',
            'operator.entitlements.view',
            'operator.users.assign_operator_access',
            'operator.users.act_as',
            'operator.privacy.requests.delete',
            'operator.entitlements.manage',
            'operator.system.settings.manage',
            'operator.system.fail2ban.view',
            'operator.system.nftables.view',
            'operator.system.backups.manage',
            'operator.audit.view',
            'operator.cookie_policies.manage',
            'operator.privacy_policies.manage',
        ],
        'plugin_auto_publish' => [
            'operator.plugin.auto_publish',
        ],
        'cms_preview' => [
            'operator.cms.preview',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        return self::MAP[$role] ?? [];
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    public static function forRoles(array $roles): array
    {
        $entitlements = [];

        foreach ($roles as $role) {
            $entitlements = [...$entitlements, ...self::forRole($role)];
        }

        $entitlements = array_values(array_unique($entitlements));
        sort($entitlements);

        return $entitlements;
    }

    /**
     * @return list<string>
     */
    public static function allEntitlements(): array
    {
        return self::forRoles(array_keys(self::MAP));
    }
}
