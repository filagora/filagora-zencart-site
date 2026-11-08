<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Memberships\Enums\MembershipStatus;
use Filagora\Memberships\Models\BillingEventLog;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The parity gate for the PayPal webhook: PayPal is configured with the path `/paypal/webhook`, so
 * it must be reachable through the site's whole middleware stack (no CSRF token, no session, no
 * login) and must be safe to deliver twice. The handler itself is tested in filagora-memberships.
 */
class PayPalWebhookParityTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'PayPal-Auth-Algo' => 'SHA256withRSA',
        'PayPal-Cert-Url' => 'https://api-m.sandbox.paypal.com/certs/cert.pem',
        'PayPal-Transmission-Id' => 'T-1',
        'PayPal-Transmission-Sig' => 'SIG',
        'PayPal-Transmission-Time' => '2026-10-17T12:00:00Z',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filagora-memberships.paypal.client_id', 'paypal-client');
        config()->set('filagora-memberships.paypal.client_secret', 'paypal-secret');
        config()->set('filagora-memberships.paypal.webhook_id', 'WH-123');
        config()->set('filagora-memberships.paypal.base_url', 'https://api-m.sandbox.paypal.com');
    }

    public function test_the_webhook_path_is_the_one_paypal_is_configured_with(): void
    {
        $this->assertSame('/paypal/webhook', route('filagora-memberships.webhooks.paypal', absolute: false));
    }

    public function test_a_verified_delivery_reaches_the_handler_without_csrf_or_session_and_is_applied_once(): void
    {
        $this->fakePayPal('SUCCESS');
        $membership = $this->pendingMembership();
        $payload = $this->activated('WH-EVENT-1');

        $first = $this->postJson('/paypal/webhook', $payload, self::HEADERS);
        $first->assertOk();
        $this->assertCount(0, $first->headers->getCookies(), 'A webhook must not start a session.');

        $this->assertSame(MembershipStatus::ACTIVE->value, $membership->refresh()->status);
        $this->assertSame(1, BillingEventLog::query()->where('event_id', 'WH-EVENT-1')->count());

        $this->postJson('/paypal/webhook', $payload, self::HEADERS)->assertOk();

        $this->assertSame(1, BillingEventLog::query()->where('event_id', 'WH-EVENT-1')->count(), 'A redelivery must not be recorded twice.');
    }

    public function test_a_forged_delivery_is_refused_and_changes_nothing(): void
    {
        $this->fakePayPal('FAILURE');
        $membership = $this->pendingMembership();

        $this->postJson('/paypal/webhook', $this->activated('WH-FORGED'), self::HEADERS)->assertStatus(400);

        $this->assertSame(MembershipStatus::PENDING->value, $membership->refresh()->status);
    }

    private function fakePayPal(string $verification): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'T', 'expires_in' => 3600]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => $verification]),
        ]);
    }

    private function pendingMembership(): UserMembership
    {
        $plan = MembershipPlan::query()->create([
            'code' => 'supporter-paypal',
            'name' => 'Supporter PayPal',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'paypal',
            'billing_interval' => 'monthly',
        ]);
        $plan->prices()->create(['currency' => 'USD', 'amount' => '5.00', 'provider_plan_id' => 'P-USD', 'is_default' => true]);

        return UserMembership::query()->create([
            'user_id' => User::factory()->create()->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::PENDING->value,
            'provider' => 'paypal',
            'subscription_name' => 'membership_plan',
            'provider_subscription_id' => 'I-PAYPAL123',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function activated(string $eventId): array
    {
        return [
            'id' => $eventId,
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => 'I-PAYPAL123',
                'status' => 'ACTIVE',
                'plan_id' => 'P-USD',
                'start_time' => '2026-10-01T10:00:00Z',
                'status_update_time' => '2026-10-17T09:00:00Z',
                'subscriber' => ['payer_id' => 'PAYER-1'],
                'billing_info' => ['next_billing_time' => '2026-11-01T10:00:00Z'],
            ],
        ];
    }
}
