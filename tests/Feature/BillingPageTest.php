<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Services\EntitlementResolver;
use Filagora\Memberships\Enums\MembershipStatus;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Filagora\Memberships\Services\Memberships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_verified_user_can_view_billing_page(): void
    {
        MembershipPlan::query()->create([
            'code' => 'community',
            'name' => 'Community',
            'description' => 'Free membership tier',
            'is_active' => true,
            'is_free' => true,
            'feature_list' => ['Forum access'],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee('Memberships & Billing', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Community')
            ->assertSee('border border-[#d8dce2] bg-white p-4', false)
            ->assertDontSee('rounded-[10px] border border-[#cabca8] bg-[#f8f6f1] p-4', false);
    }

    public function test_legacy_billing_page_redirects_to_profile_billing_tab(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertRedirect(route('profile.show', ['tab' => 'billing']));
    }

    public function test_user_can_join_free_membership_plan(): void
    {
        $plan = MembershipPlan::query()->create([
            'code' => 'community',
            'name' => 'Community',
            'is_active' => true,
            'is_free' => true,
            'entitlements' => ['forums.community.view'],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('billing.subscribe', $plan))
            ->assertRedirect(route('profile.show', ['tab' => 'billing']));

        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'local',
        ]);
    }

    public function test_subscription_names_are_plan_specific_for_multi_membership_support(): void
    {
        $service = app(Memberships::class);

        $monthly = MembershipPlan::query()->create([
            'code' => 'supporter-monthly',
            'name' => 'Supporter Monthly',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
        ]);
        $monthly->prices()->create([
            'currency' => 'USD',
            'amount' => 9.99,
            'provider_plan_id' => 'price_monthly',
            'is_default' => true,
        ]);

        $yearly = MembershipPlan::query()->create([
            'code' => 'supporter-yearly',
            'name' => 'Supporter Yearly',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
        ]);
        $yearly->prices()->create([
            'currency' => 'USD',
            'amount' => 99.99,
            'provider_plan_id' => 'price_yearly',
            'is_default' => true,
        ]);

        $this->assertSame('membership_supporter_monthly', $service->subscriptionNameFor($monthly));
        $this->assertSame('membership_supporter_yearly', $service->subscriptionNameFor($yearly));
        $this->assertNotSame(
            $service->subscriptionNameFor($monthly),
            $service->subscriptionNameFor($yearly),
        );
    }

    public function test_entitlements_merge_across_multiple_active_memberships(): void
    {
        $service = app(Memberships::class);
        $user = User::factory()->create();

        $forumsPlan = MembershipPlan::query()->create([
            'code' => 'commercial-plus',
            'name' => 'Commercial Plus',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
            'entitlements' => ['forums.commercial_help_wanted.view'],
        ]);
        $forumsPlan->prices()->create([
            'currency' => 'USD',
            'amount' => 9.99,
            'provider_plan_id' => 'price_commercial',
            'is_default' => true,
        ]);

        $downloadsPlan = MembershipPlan::query()->create([
            'code' => 'premium-downloads',
            'name' => 'Premium Downloads',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
            'entitlements' => ['downloads.premium_plugins'],
        ]);
        $downloadsPlan->prices()->create([
            'currency' => 'USD',
            'amount' => 4.99,
            'provider_plan_id' => 'price_downloads',
            'is_default' => true,
        ]);

        UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $forumsPlan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'stripe',
            'subscription_name' => $service->subscriptionNameFor($forumsPlan),
            'provider_subscription_id' => 'sub_commercial',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => $forumsPlan->entitlementList(),
        ]);

        UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $downloadsPlan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'stripe',
            'subscription_name' => $service->subscriptionNameFor($downloadsPlan),
            'provider_subscription_id' => 'sub_downloads',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => $downloadsPlan->entitlementList(),
        ]);

        $entitlements = app(EntitlementResolver::class)->entitlementsFor($user->fresh());

        $this->assertContains('forums.commercial_help_wanted.view', $entitlements);
        $this->assertContains('downloads.premium_plugins', $entitlements);
    }

    public function test_active_membership_with_empty_snapshot_falls_back_to_plan_entitlements(): void
    {
        $plan = MembershipPlan::query()->create([
            'code' => 'chwm',
            'name' => 'Commercial Help Wanted Membership',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'entitlements' => ['forums.chwm.reply'],
        ]);

        $user = User::factory()->create();

        UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'stripe',
            'subscription_name' => app(Memberships::class)->subscriptionNameFor($plan),
            'provider_subscription_id' => 'sub_chwm',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => [],
        ]);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee('forums.chwm.reply');
    }

    public function test_canceling_membership_does_not_show_cancel_button_again(): void
    {
        $plan = MembershipPlan::query()->create([
            'code' => 'chwm',
            'name' => 'Commercial Help Wanted Membership',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'entitlements' => ['forums.chwm.reply'],
        ]);

        $user = User::factory()->create();

        UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::CANCELING->value,
            'provider' => 'stripe',
            'subscription_name' => app(Memberships::class)->subscriptionNameFor($plan),
            'provider_subscription_id' => 'sub_chwm',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => ['forums.chwm.reply'],
        ]);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee('Status: canceling')
            ->assertSee('Cancels at period end')
            ->assertDontSee('>Cancel</button>', false);
    }

    public function test_pending_membership_hides_duplicate_subscribe_button_for_same_plan(): void
    {
        $plan = MembershipPlan::query()->create([
            'code' => 'supporter-paypal',
            'name' => 'Supporter PayPal',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'paypal',
            'billing_interval' => 'monthly',
        ]);

        $user = User::factory()->create();

        UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::PENDING->value,
            'provider' => 'paypal',
            'subscription_name' => app(Memberships::class)->subscriptionNameFor($plan),
            'provider_subscription_id' => 'I-PAYPAL123',
            'meta' => [
                'subscription_name' => app(Memberships::class)->subscriptionNameFor($plan),
            ],
        ]);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee('Status: pending')
            ->assertSee('Pending Confirmation')
            ->assertDontSee('Subscribe with PayPal');
    }
}
