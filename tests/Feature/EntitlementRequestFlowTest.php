<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Enums\EntitlementRequestStatus;
use Filagora\Entitlements\Models\EntitlementDefinition;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class EntitlementRequestFlowTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_authenticated_user_can_submit_requestable_entitlement_request_from_profile(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        EntitlementDefinition::query()->create([
            'key' => 'api.plugins.download',
            'label' => 'API Plugins Download',
            'namespace' => 'api.plugins',
            'is_grantable' => true,
            'is_requestable' => true,
            'supports_wildcard_children' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('profile.entitlement-requests.store'), [
                'entitlement_key' => 'api.plugins.download',
                'request_reason' => 'Need access for plugin integration testing.',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'entitlements']))
            ->assertSessionHas('profile_status', __('profile.flash.entitlement_request_submitted'));

        $request = EntitlementRequest::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($request);
        $this->assertSame('api.plugins.download', $request?->entitlement_key);
        $this->assertSame(EntitlementRequestStatus::PENDING, $request?->status);
    }

    public function test_user_cannot_request_non_requestable_entitlement(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        EntitlementDefinition::query()->create([
            'key' => 'admin.audit.view',
            'label' => 'Admin Audit View',
            'namespace' => 'admin.audit',
            'is_grantable' => true,
            'is_requestable' => false,
            'supports_wildcard_children' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('profile.show', ['tab' => 'entitlements']))
            ->post(route('profile.entitlement-requests.store'), [
                'entitlement_key' => 'admin.audit.view',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'entitlements']))
            ->assertSessionHasErrors(['entitlement_key'], null, 'entitlementRequest');

        $this->assertSame(0, EntitlementRequest::query()->count());
    }

    public function test_user_cannot_submit_duplicate_pending_request_for_same_entitlement(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        EntitlementDefinition::query()->create([
            'key' => 'downloads.premium_plugins',
            'label' => 'Premium Plugin Downloads',
            'namespace' => 'downloads',
            'is_grantable' => true,
            'is_requestable' => true,
            'supports_wildcard_children' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        EntitlementRequest::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'downloads.premium_plugins',
            'status' => EntitlementRequestStatus::PENDING->value,
            'requested_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->from(route('profile.show', ['tab' => 'entitlements']))
            ->post(route('profile.entitlement-requests.store'), [
                'entitlement_key' => 'downloads.premium_plugins',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'entitlements']))
            ->assertSessionHasErrors(['entitlement_key'], null, 'entitlementRequest');

        $this->assertSame(1, EntitlementRequest::query()->where('user_id', $user->id)->count());
    }

    public function test_user_cannot_request_entitlement_they_already_have(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        EntitlementDefinition::query()->updateOrCreate(
            ['key' => 'api.plugins.read'],
            [
                'label' => 'API Plugins Read',
                'namespace' => 'api.plugins',
                'is_grantable' => true,
                'is_requestable' => true,
                'supports_wildcard_children' => false,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.read',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->from(route('profile.show', ['tab' => 'entitlements']))
            ->post(route('profile.entitlement-requests.store'), [
                'entitlement_key' => 'api.plugins.read',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'entitlements']))
            ->assertSessionHasErrors(['entitlement_key'], null, 'entitlementRequest');

        $this->assertSame(0, EntitlementRequest::query()->count());
    }

    public function test_entitlement_request_history_uses_custom_profile_pagination(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, 21) as $index) {
            EntitlementRequest::query()->create([
                'user_id' => $user->id,
                'entitlement_key' => 'downloads.request_'.$index,
                'status' => EntitlementRequestStatus::PENDING->value,
                'request_reason' => 'Entitlement request '.$index,
                'requested_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'entitlements']))
            ->assertOk()
            ->assertSee('Entitlement requests pagination')
            ->assertSee('>First<', false)
            ->assertSee('>Previous<', false)
            ->assertSee('>Next<', false)
            ->assertSee('>Last<', false)
            ->assertSee('downloads.request_1')
            ->assertDontSee('downloads.request_21');
    }
}
