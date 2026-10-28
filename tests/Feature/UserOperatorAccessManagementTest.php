<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserOperatorAccessManager;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOperatorAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_privileged_staff_can_assign_standard_operator_access_but_not_privileged_access(): void
    {
        $actor = $this->staffUser([
            'operator.dashboard.access',
            'operator.users.view',
            'operator.users.update',
        ]);
        $managedUser = User::factory()->create();

        app(UserOperatorAccessManager::class)->sync($managedUser, [
            'operator.plugin.auto_publish',
            'operator.system.settings.manage',
        ], $actor);

        $managedUser->refresh();

        $this->assertTrue($managedUser->entitlementGrants()->active()->where('entitlement_key', 'operator.plugin.auto_publish')->exists());
        $this->assertFalse($managedUser->entitlementGrants()->active()->where('entitlement_key', 'operator.system.settings.manage')->exists());
    }

    public function test_sync_replaces_direct_operator_grants_for_user_admin_workflow(): void
    {
        $actor = $this->staffUser([
            'operator.dashboard.access',
            'operator.users.view',
            'operator.users.update',
        ]);
        $managedUser = User::factory()->create();

        UserEntitlementGrant::query()->create([
            'user_id' => $managedUser->id,
            'entitlement_key' => 'operator.plugin.auto_publish',
            'grant_type' => 'manual',
            'granted_by_id' => $actor->id,
            'starts_at' => now()->subDay(),
        ]);

        app(UserOperatorAccessManager::class)->sync($managedUser, [
            'operator.forum.moderate',
        ], $actor);

        $this->assertTrue($managedUser->entitlementGrants()->active()->where('entitlement_key', 'operator.forum.moderate')->exists());

        $revokedGrant = $managedUser->entitlementGrants()
            ->where('entitlement_key', 'operator.plugin.auto_publish')
            ->latest('id')
            ->first();

        $this->assertNotNull($revokedGrant);
        $this->assertNotNull($revokedGrant->revoked_at);
        $this->assertSame($actor->id, $revokedGrant->revoked_by_id);
    }

    public function test_privileged_staff_can_assign_privileged_operator_access(): void
    {
        $actor = $this->staffUser([
            'operator.dashboard.access',
            'operator.users.view',
            'operator.users.update',
            'operator.users.assign_operator_access',
        ]);
        $managedUser = User::factory()->create();

        app(UserOperatorAccessManager::class)->sync($managedUser, [
            'operator.system.settings.manage',
        ], $actor);

        $this->assertTrue($managedUser->entitlementGrants()->active()->where('entitlement_key', 'operator.system.settings.manage')->exists());
    }

    /**
     * @param  list<string>  $entitlements
     */
    private function staffUser(array $entitlements): User
    {
        $user = User::factory()->create();

        foreach ($entitlements as $entitlement) {
            UserEntitlementGrant::query()->create([
                'user_id' => $user->id,
                'entitlement_key' => $entitlement,
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        }

        return $user;
    }
}
