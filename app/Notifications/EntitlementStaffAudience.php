<?php

namespace App\Notifications;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Notifications\Contracts\StaffAudience;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who holds an entitlement, for the people a staff alert goes to. Access is answered one person
 * at a time, so this narrows to the people who could hold the key: those with a current direct
 * grant of it (or a wildcard grant), and the super administrators who hold every entitlement.
 * `Recipients` then checks each one's access again, so over-listing is harmless.
 */
final class EntitlementStaffAudience implements StaffAudience
{
    /**
     * @return iterable<Authenticatable>
     */
    public function holding(string $access): iterable
    {
        $granted = UserEntitlementGrant::query()
            ->active()
            ->where(fn ($query) => $query->where('entitlement_key', $access)->orWhere('entitlement_key', 'like', '%*'))
            ->pluck('user_id');

        $role = (string) config('filagora-zencart.super_admin_role');
        $superUsers = $role !== '' && method_exists(User::class, 'scopeRole')
            ? User::query()->role($role)->pluck('id')
            : collect();

        return User::query()
            ->whereIn('id', $granted->merge($superUsers)->unique()->values())
            ->orderBy('id')
            ->get();
    }
}
