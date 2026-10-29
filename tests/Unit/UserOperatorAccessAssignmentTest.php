<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\UserOperatorAccessAssignment;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOperatorAccessAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_entitlement_keys_include_privileged_operator_access_for_privileged_actor(): void
    {
        $actor = $this->actorWithEntitlements(['operator.users.assign_operator_access']);

        $visible = UserOperatorAccessAssignment::visibleEntitlementKeys($actor);

        $this->assertContains('operator.system.settings.manage', $visible);
        $this->assertContains('operator.users.act_as', $visible);
        $this->assertContains('operator.users.assign_operator_access', $visible);
    }

    public function test_visible_entitlement_keys_exclude_privileged_operator_access_for_standard_staff_actor(): void
    {
        $actor = $this->actorWithEntitlements(['operator.users.update']);

        $visible = UserOperatorAccessAssignment::visibleEntitlementKeys($actor);

        $this->assertContains('operator.newsletter.manage', $visible);
        $this->assertContains('operator.plugin.auto_publish', $visible);
        $this->assertNotContains('operator.system.settings.manage', $visible);
        $this->assertNotContains('operator.users.act_as', $visible);
        $this->assertNotContains('operator.users.assign_operator_access', $visible);
    }

    public function test_sanitize_removes_unknown_and_privileged_entitlements_for_standard_staff_actor(): void
    {
        $actor = $this->actorWithEntitlements(['operator.users.update']);

        $this->assertSame(
            ['operator.forum.moderate'],
            UserOperatorAccessAssignment::sanitize($actor, [
                'operator.forum.moderate',
                'operator.users.act_as',
                'operator.system.settings.manage',
                'made.up.entitlement',
            ])
        );
    }

    /**
     * @param  list<string>  $entitlements
     */
    private function actorWithEntitlements(array $entitlements): User
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
