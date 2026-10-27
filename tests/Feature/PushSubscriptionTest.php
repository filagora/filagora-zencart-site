<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Push\Models\PushSubscription;
use Filagora\Push\Services\PushSubscriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    /**
     * @return array<string, mixed>
     */
    private function payload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BExamplePublicKeyValue',
                'auth' => 'ExampleAuthSecret',
            ],
            'content_encoding' => 'aes128gcm',
        ];
    }

    public function test_guest_cannot_store_a_push_subscription(): void
    {
        $this->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_store_a_push_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Test Runner)'])
            ->postJson(route('push.subscriptions.store'), $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        $subscription = PushSubscription::query()->firstOrFail();
        $this->assertSame($user->id, (int) $subscription->subscribable_id);
        $this->assertSame(User::class, $subscription->subscribable_type);
        $this->assertSame('https://fcm.googleapis.com/fcm/send/abc123', $subscription->endpoint);
        $this->assertSame('Mozilla/5.0 (Test Runner)', $subscription->user_agent);
        $this->assertNotNull($subscription->last_used_at);
    }

    public function test_storing_the_same_endpoint_twice_refreshes_rather_than_duplicates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push.subscriptions.store'), $this->payload())->assertOk();
        $this->travel(1)->hours();
        $this->actingAs($user)->postJson(route('push.subscriptions.store'), $this->payload())->assertOk();

        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_store_rejects_a_malformed_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscriptions.store'), ['endpoint' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys']);
    }

    public function test_store_rejects_an_endpoint_that_is_not_a_known_push_service(): void
    {
        $user = User::factory()->create();

        foreach ([
            'http://169.254.169.254/latest/meta-data/',
            'https://localhost:6379/',
            'https://fcm.googleapis.com.attacker.example/fcm/send/x',
            'http://fcm.googleapis.com/fcm/send/x', // not https
        ] as $endpoint) {
            $this->actingAs($user)
                ->postJson(route('push.subscriptions.store'), $this->payload($endpoint))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['endpoint']);
        }

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_store_accepts_each_mainstream_push_service_host(): void
    {
        $user = User::factory()->create();

        foreach ([
            'https://fcm.googleapis.com/fcm/send/abc',
            'https://updates.push.services.mozilla.com/wpush/v2/abc',
            'https://abcdef.notify.windows.com/w/?token=abc',
            'https://web.push.apple.com/QABC',
        ] as $endpoint) {
            $this->actingAs($user)
                ->postJson(route('push.subscriptions.store'), $this->payload($endpoint))
                ->assertOk();
        }
    }

    public function test_service_worker_resync_route_is_csrf_exempt(): void
    {
        $user = User::factory()->create();
        app(PushSubscriptions::class)->store($user, 'https://fcm.googleapis.com/fcm/send/old123', 'k', 't');

        // No CSRF token, plain (non-JSON) post — mirrors the service worker
        // fetch from a background context. The page-driven store route would
        // 419 here; this one is exempt.
        $this->actingAs($user)
            ->post(route('push.subscriptions.resync'), [
                ...$this->payload(),
                'old_endpoint' => 'https://fcm.googleapis.com/fcm/send/old123',
            ])
            ->assertOk();

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame('https://fcm.googleapis.com/fcm/send/abc123', PushSubscription::query()->firstOrFail()->endpoint);
    }

    public function test_service_worker_resync_route_still_requires_auth(): void
    {
        $this->post(route('push.subscriptions.resync'), [
            ...$this->payload(),
            'old_endpoint' => 'https://fcm.googleapis.com/fcm/send/old123',
        ])->assertRedirect();

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_resync_requires_the_old_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscriptions.resync'), $this->payload())
            ->assertUnprocessable();

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_resync_rejects_an_old_endpoint_the_caller_does_not_own(): void
    {
        $owner = User::factory()->create();
        app(PushSubscriptions::class)->store($owner, 'https://fcm.googleapis.com/fcm/send/victim123', 'k', 't');

        $attacker = User::factory()->create();

        // Mirrors a forged cross-site request riding the attacker's own
        // session: even though it's authenticated, it names an endpoint the
        // caller has never subscribed, so it must not be allowed to plant a
        // new subscription (their own attacker-controlled one) on this
        // account, and it must not touch the victim's real row either.
        $this->actingAs($attacker)
            ->post(route('push.subscriptions.resync'), [
                ...$this->payload('https://fcm.googleapis.com/fcm/send/attacker456'),
                'old_endpoint' => 'https://fcm.googleapis.com/fcm/send/victim123',
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame(
            'https://fcm.googleapis.com/fcm/send/victim123',
            PushSubscription::query()->firstOrFail()->endpoint,
        );
    }

    public function test_user_can_delete_a_push_subscription_by_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('push.subscriptions.store'), $this->payload())->assertOk();

        $this->actingAs($user)
            ->deleteJson(route('push.subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])
            ->assertOk();

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_prune_stale_command_drops_long_unused_subscriptions_only(): void
    {
        $user = User::factory()->create();

        $fresh = app(PushSubscriptions::class)->store($user, 'https://fcm.googleapis.com/fcm/send/fresh', 'k', 't');
        $fresh->forceFill(['last_used_at' => now()->subDays(10)])->save();

        $stale = app(PushSubscriptions::class)->store($user, 'https://fcm.googleapis.com/fcm/send/stale', 'k', 't');
        $stale->forceFill(['last_used_at' => now()->subDays(400)])->save();

        $this->artisan('filagora:push:prune-stale')->assertSuccessful();

        $this->assertTrue(PushSubscription::query()->whereKey($fresh->id)->exists());
        $this->assertFalse(PushSubscription::query()->whereKey($stale->id)->exists());
    }
}
