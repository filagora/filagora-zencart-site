<?php

namespace App\Support;

use App\Models\User;
use Filagora\Entitlements\Services\AuthorizationService;

class UserOperatorAccessAssignment
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'operator.dashboard.access' => 'Dashboard Access',
        'operator.queue_monitors.view' => 'Queue Monitors',
        'operator.forum.moderate' => 'Forum Moderation',
        'operator.forum.view_unapproved' => 'View Unapproved Forum Content',
        'operator.forum.bypass_approval' => 'Bypass Forum Approval',
        'operator.plugin.moderate' => 'Plugin Moderation',
        'operator.plugin.auto_publish' => 'Plugin Auto Publish',
        'operator.newsletter.manage' => 'Newsletter Management',
        'operator.cms.manage' => 'CMS Management',
        'operator.cms.preview' => 'CMS Draft Preview',
        'operator.users.view' => 'User Directory Access',
        'operator.users.update' => 'User Editing',
        'operator.users.act_as' => 'User Impersonation',
        'operator.privacy.requests.view' => 'Privacy Request Access',
        'operator.privacy.requests.update' => 'Privacy Request Updates',
        'operator.entitlements.view' => 'Entitlement Visibility',
        'operator.users.assign_operator_access' => 'Operator Access Management',
        'operator.privacy.requests.delete' => 'Privacy Request Deletion',
        'operator.entitlements.manage' => 'Entitlement Management',
        'operator.system.settings.manage' => 'System Settings',
        'operator.system.fail2ban.view' => 'Fail2ban Dashboard',
        'operator.system.nftables.view' => 'Nftables Dashboard',
        'operator.system.backups.manage' => 'Database Backups',
        'operator.audit.view' => 'Audit Access',
        'operator.cookie_policies.manage' => 'Cookie Policy Management',
        'operator.privacy_policies.manage' => 'Privacy Policy Management',
    ];

    /**
     * @var list<string>
     */
    private const STANDARD_ASSIGNABLE = [
        'operator.dashboard.access',
        'operator.queue_monitors.view',
        'operator.forum.moderate',
        'operator.forum.view_unapproved',
        'operator.forum.bypass_approval',
        'operator.plugin.moderate',
        'operator.plugin.auto_publish',
        'operator.newsletter.manage',
        'operator.cms.manage',
        'operator.cms.preview',
        'operator.users.view',
        'operator.users.update',
        'operator.privacy.requests.view',
        'operator.privacy.requests.update',
        'operator.entitlements.view',
    ];

    /**
     * @var list<string>
     */
    private const PRIVILEGED_ASSIGNABLE = [
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
    ];

    /**
     * @return list<string>
     */
    public static function visibleEntitlementKeys(?User $actor): array
    {
        $keys = self::STANDARD_ASSIGNABLE;

        if (app(AuthorizationService::class)->allows($actor, 'operator.users.assign_operator_access')) {
            $keys = [...$keys, ...self::PRIVILEGED_ASSIGNABLE];
        }

        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function managedEntitlementKeys(): array
    {
        $keys = [...self::STANDARD_ASSIGNABLE, ...self::PRIVILEGED_ASSIGNABLE];
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    public static function optionsFor(?User $actor): array
    {
        $options = [];

        foreach (self::visibleEntitlementKeys($actor) as $entitlementKey) {
            $options[$entitlementKey] = self::labelFor($entitlementKey);
        }

        return $options;
    }

    public static function labelFor(string $entitlementKey): string
    {
        return self::LABELS[$entitlementKey] ?? $entitlementKey;
    }

    public static function canManageSuperAdmin(?User $actor): bool
    {
        return app(AuthorizationService::class)->allows($actor, 'operator.users.assign_operator_access');
    }

    /**
     * @param  list<string>  $entitlementKeys
     * @return list<string>
     */
    public static function labelsFor(array $entitlementKeys): array
    {
        return array_values(array_map(
            static fn (string $entitlementKey): string => self::labelFor($entitlementKey),
            self::normalize($entitlementKeys),
        ));
    }

    /**
     * @param  list<string>  $requestedEntitlements
     * @param  list<string>|null  $availableEntitlementKeys
     * @return list<string>
     */
    public static function sanitize(?User $actor, array $requestedEntitlements, ?array $availableEntitlementKeys = null): array
    {
        $allowed = self::normalize($availableEntitlementKeys ?? self::visibleEntitlementKeys($actor));

        if ($allowed === []) {
            return [];
        }

        return array_values(array_intersect(self::normalize($requestedEntitlements), $allowed));
    }

    /**
     * @param  list<string>  $entitlementKeys
     * @return list<string>
     */
    private static function normalize(array $entitlementKeys): array
    {
        $normalized = array_values(array_filter(
            array_map(static fn (mixed $entitlementKey): string => is_string($entitlementKey) ? trim($entitlementKey) : '', $entitlementKeys),
            static fn (string $entitlementKey): bool => $entitlementKey !== ''
        ));

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
