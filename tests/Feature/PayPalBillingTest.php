<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Memberships\Enums\MembershipStatus;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Filagora\Memberships\Services\Memberships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PayPalBillingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filagora-memberships.paypal.client_id', 'paypal-client');
        config()->set('filagora-memberships.paypal.client_secret', 'paypal-secret');
        config()->set('filagora-memberships.paypal.webhook_id', 'WH-123');
        config()->set('filagora-memberships.paypal.base_url', 'https://api-m.sandbox.paypal.com');
    }

    public function test_user_can_start_paypal_subscription(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response([
                'id' => 'I-PAYPAL123',
                'status' => 'APPROVAL_PENDING',
                'links' => [
                    [
                        'rel' => 'approve',
                        'href' => 'https://www.paypal.com/checkoutnow?token=BA-123',
                    ],
                ],
            ], 201),
        ]);

        $plan = MembershipPlan::query()->create([
            'code' => 'supporter-paypal',
            'name' => 'Supporter PayPal',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'paypal',
            'billing_interval' => 'monthly',
        ]);
        $plan->prices()->create([
            'currency' => 'USD',
            'amount' => 9.99,
            'provider_plan_id' => 'P-12345678901234567',
            'is_default' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('billing.subscribe', $plan))
            ->assertRedirect('https://www.paypal.com/checkoutnow?token=BA-123');

        $membership = UserMembership::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('paypal', $membership->provider);
        $this->assertSame(MembershipStatus::PENDING->value, $membership->status);
        $this->assertSame('I-PAYPAL123', $membership->provider_subscription_id);
        $this->assertSame('P-12345678901234567', data_get($membership->meta, 'provider_price_id'));

        Http::assertSent(function ($request) use ($membership): bool {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions'
                && $request['plan_id'] === 'P-12345678901234567'
                && $request['custom_id'] === 'membership:'.$membership->id;
        });
    }

    public function test_user_can_choose_a_non_default_currency_when_subscribing(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response([
                'id' => 'I-PAYPAL-EUR',
                'status' => 'APPROVAL_PENDING',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=BA-EUR'],
                ],
            ], 201),
        ]);

        $plan = MembershipPlan::query()->create([
            'code' => 'supporter-paypal-multi',
            'name' => 'Supporter PayPal Multi',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'paypal',
            'billing_interval' => 'monthly',
        ]);
        $plan->prices()->create([
            'currency' => 'USD',
            'amount' => 9.99,
            'provider_plan_id' => 'P-USD-PLAN',
            'is_default' => true,
        ]);
        $plan->prices()->create([
            'currency' => 'EUR',
            'amount' => 8.99,
            'provider_plan_id' => 'P-EUR-PLAN',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('billing.subscribe', $plan), ['currency' => 'EUR'])
            ->assertRedirect('https://www.paypal.com/checkoutnow?token=BA-EUR');

        $membership = UserMembership::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('EUR', data_get($membership->meta, 'currency'));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions'
            && $request['plan_id'] === 'P-EUR-PLAN');
    }

    public function test_subscribing_with_an_unconfigured_currency_falls_back_to_the_default_price(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response([
                'id' => 'I-PAYPAL-FALLBACK',
                'status' => 'APPROVAL_PENDING',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=BA-FALLBACK'],
                ],
            ], 201),
        ]);

        $plan = MembershipPlan::query()->create([
            'code' => 'supporter-paypal-fallback',
            'name' => 'Supporter PayPal Fallback',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'paypal',
            'billing_interval' => 'monthly',
        ]);
        $plan->prices()->create([
            'currency' => 'USD',
            'amount' => 9.99,
            'provider_plan_id' => 'P-USD-PLAN',
            'is_default' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('billing.subscribe', $plan), ['currency' => 'GBP'])
            ->assertRedirect('https://www.paypal.com/checkoutnow?token=BA-FALLBACK');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions'
            && $request['plan_id'] === 'P-USD-PLAN');
    }

    public function test_user_can_cancel_paypal_membership(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-PAYPAL123/cancel' => Http::response([], 204),
        ]);

        $plan = MembershipPlan::query()->create([
            'code' => 'supporter-paypal',
            'name' => 'Supporter PayPal',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'paypal',
            'billing_interval' => 'monthly',
        ]);
        $plan->prices()->create([
            'currency' => 'USD',
            'amount' => 9.99,
            'provider_plan_id' => 'P-12345678901234567',
            'is_default' => true,
        ]);

        $user = User::factory()->create();

        $membership = UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'paypal',
            'subscription_name' => app(Memberships::class)->subscriptionNameFor($plan),
            'provider_subscription_id' => 'I-PAYPAL123',
            'current_period_end' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('billing.cancel', $membership))
            ->assertRedirect(route('profile.show', ['tab' => 'billing']))
            ->assertSessionHas('billing_status', __('billing.messages.membership_canceling'));

        $membership->refresh();

        $this->assertSame(MembershipStatus::CANCELING->value, $membership->status);
        $this->assertNotNull($membership->canceled_at);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-PAYPAL123/cancel');
    }
}
