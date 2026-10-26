<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\PluginPolicy;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_can_moderate_any_and_specific_plugin(): void
    {
        $staff = User::factory()->create();
        /* role forum_user carries no operator entitlement */
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $plugin = $this->createPluginRecord(owner: User::factory()->create());
        $policy = new PluginPolicy;

        $this->assertTrue($policy->moderateAny($staff));
        $this->assertTrue($policy->moderate($staff, $plugin));
    }

    public function test_forum_user_cannot_moderate_any_or_specific_plugin(): void
    {
        $user = User::factory()->create();
        /* role forum_user carries no operator entitlement */

        $plugin = $this->createPluginRecord(owner: User::factory()->create());
        $policy = new PluginPolicy;

        $this->assertFalse($policy->moderateAny($user));
        $this->assertFalse($policy->moderate($user, $plugin));
    }

    /**
     * Filament falls back to *allowing* an action when a policy exists but
     * is missing the specific ability method it checks (non-strict mode) —
     * PluginPolicy previously had no viewAny()/create()/delete()/deleteAny(),
     * so any authenticated user could reach the dashboard Plugin resource.
     * This exercises the actual Filament entry point, not just the raw
     * policy method, since that's where the gap manifested.
     */
    public function test_submit_release_is_allowed_for_owner_even_without_staff_role(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->createPluginRecord(owner: $owner);
        $policy = new PluginPolicy;

        $this->assertTrue($policy->submitRelease($owner, $plugin));
    }

    public function test_submit_release_is_allowed_for_staff_even_when_not_owner(): void
    {
        $owner = User::factory()->create();
        $plugin = $this->createPluginRecord(owner: $owner);

        $staff = User::factory()->create();
        /* role forum_user carries no operator entitlement */
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $policy = new PluginPolicy;

        $this->assertTrue($policy->submitRelease($staff, $plugin));
    }

    public function test_submit_release_is_allowed_for_non_owner_non_staff(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $plugin = $this->createPluginRecord(owner: $owner);

        /* role forum_user carries no operator entitlement */

        $policy = new PluginPolicy;

        $this->assertTrue($policy->submitRelease($otherUser, $plugin));
    }

    public function test_submit_release_is_allowed_when_plugin_has_no_owner_and_user_is_not_staff(): void
    {
        $user = User::factory()->create();
        $plugin = $this->createPluginRecord(owner: null);
        $policy = new PluginPolicy;

        $this->assertTrue($policy->submitRelease($user, $plugin));
    }

    private function createPluginRecord(?User $owner): Plugin
    {
        $type = PluginType::query()->create([
            'name' => 'Policy Type',
            'slug' => 'policy-type-'.str()->lower(str()->random(8)),
            'is_active' => true,
            'position' => 100,
        ]);

        return Plugin::query()->create([
            'name' => 'Policy Plugin '.str()->random(5),
            'slug' => 'policy-plugin-'.str()->lower(str()->random(8)),
            'plugin_type_id' => $type->id,
            'owner_id' => $owner?->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
    }
}
