<?php

namespace App\Services;

use App\Models\User;
use App\Support\UserOperatorAccessAssignment;
use Filagora\Entitlements\Models\UserEntitlementGrant;

class UserOperatorAccessManager
{
    /**
     * @return list<string>
     */
    public function selectedOperatorEntitlements(User $user): array
    {
        /** @var list<string> $entitlementKeys */
        $entitlementKeys = UserEntitlementGrant::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('grant_type', 'manual')
            ->whereIn('entitlement_key', UserOperatorAccessAssignment::managedEntitlementKeys())
            ->orderBy('entitlement_key')
            ->pluck('entitlement_key')
            ->all();

        return array_values(array_unique($entitlementKeys));
    }

    /**
     * The selected operator entitlements of several users, in one query.
     *
     * @param  list<int|string>  $userIds
     * @return array<int|string, list<string>>
     */
    public function selectedOperatorEntitlementsForMany(array $userIds): array
    {
        return UserEntitlementGrant::query()
            ->active()
            ->whereIn('user_id', $userIds)
            ->where('grant_type', 'manual')
            ->whereIn('entitlement_key', UserOperatorAccessAssignment::managedEntitlementKeys())
            ->orderBy('entitlement_key')
            ->get(['user_id', 'entitlement_key'])
            ->groupBy('user_id')
            ->map(fn ($grants): array => $grants->pluck('entitlement_key')->unique()->values()->all())
            ->all();
    }

    /**
     * @param  list<string>  $requestedEntitlements
     */
    public function sync(User $user, array $requestedEntitlements, ?User $actor): void
    {
        $allowedEntitlements = UserOperatorAccessAssignment::sanitize($actor, $requestedEntitlements);
        $manageableEntitlements = UserOperatorAccessAssignment::visibleEntitlementKeys($actor);

        $activeManualGrants = UserEntitlementGrant::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('grant_type', 'manual')
            ->whereIn('entitlement_key', $manageableEntitlements)
            ->get();

        $grantsByEntitlement = $activeManualGrants
            ->groupBy('entitlement_key');

        foreach ($manageableEntitlements as $entitlementKey) {
            $isRequested = in_array($entitlementKey, $allowedEntitlements, true);
            $activeGrants = $grantsByEntitlement->get($entitlementKey);

            if ($isRequested) {
                if ($activeGrants !== null && $activeGrants->isNotEmpty()) {
                    continue;
                }

                // A new row each time: an earlier grant and its revocation stay on record.
                UserEntitlementGrant::query()->create([
                    'user_id' => $user->id,
                    'entitlement_key' => $entitlementKey,
                    'grant_type' => 'manual',
                    'granted_by_id' => $actor?->id,
                    'starts_at' => now(),
                    'reason' => 'Granted via the user admin operator access editor.',
                    'meta' => ['managed_via' => 'user_admin'],
                ]);

                continue;
            }

            foreach ($activeGrants ?? [] as $activeGrant) {
                $activeGrant->revoked_at = now();
                $activeGrant->revoked_by_id = $actor?->id;
                $activeGrant->meta = array_merge(
                    is_array($activeGrant->meta) ? $activeGrant->meta : [],
                    ['managed_via' => 'user_admin']
                );
                $activeGrant->save();
            }
        }
    }
}
