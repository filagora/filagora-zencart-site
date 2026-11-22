<?php

namespace App\Notifications;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Notifications\Contracts\StaffAudience;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

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

        // Roles here are the plain `roles` and `model_has_roles` tables (`HasSiteRoles`), not a
        // permissions package, so read them the way `hasRole()` does.
        $role = (string) config('filagora-zencart.super_admin_role');
        $superUsers = $role === '' ? collect() : DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', $role)
            ->pluck('model_has_roles.model_id');

        return User::query()
            ->whereIn('id', $granted->merge($superUsers)->unique()->values())
            ->orderBy('id')
            ->get();
    }
}
