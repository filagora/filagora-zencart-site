<?php

namespace Tests\Feature;

use App\Jobs\ProcessFaiveloWebhookEvent;
use App\Models\User;
use App\Services\Faivelo\FaiveloWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class FaiveloWebhookControllerTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('queue.default', 'sync');
        config()->set('services.faivelo.webhook_signing_key', 'faivelo-test-key');
    }

    public function test_hard_bounce_suppresses_the_member(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'receives_email' => true]);

        $this->postWebhook($this->event('email.bounced', ['to' => ['member@example.com'], 'bounce' => ['type' => 'hard', 'reason' => 'Mailbox does not exist']]))
            ->assertOk()->assertJson(['status' => 'processed']);

        $user->refresh();
        $this->assertSame('bounced', $user->email_status);
        $this->assertNotNull($user->email_bounced_at);
        $this->assertFalse((bool) $user->receives_email);
    }

    public function test_soft_bounce_leaves_the_member_alone(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'receives_email' => true]);

        $this->postWebhook($this->event('email.bounced', ['to' => ['member@example.com'], 'bounce' => ['type' => 'soft', 'reason' => 'Mailbox full']]))
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->email_bounced_at);
        $this->assertTrue((bool) $user->receives_email);
    }

    public function test_complaint_suppresses_the_member(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'receives_email' => true]);

        $this->postWebhook($this->event('email.complained', ['to' => 'member@example.com']))->assertOk();

        $user->refresh();
        $this->assertSame('complained', $user->email_status);
        $this->assertNotNull($user->email_complained_at);
        $this->assertFalse((bool) $user->receives_email);
    }

    public function test_delivery_records_the_event_time(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->postWebhook($this->event('email.delivered', ['to' => ['member@example.com']], createdAt: '2026-11-18T10:00:00Z'))->assertOk();

        $user->refresh();
        $this->assertSame('deliverable', $user->email_status);
        $this->assertStringStartsWith('2026-11-18 10:00:00', (string) $user->email_last_delivered_at);
    }

    public function test_a_late_delivery_does_not_clear_a_bounce(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'receives_email' => true]);

        $this->postWebhook($this->event('email.bounced', ['to' => ['member@example.com'], 'bounce' => ['type' => 'hard']], id: 'evt_b'))->assertOk();
        $this->postWebhook($this->event('email.delivered', ['to' => ['member@example.com']], id: 'evt_d'))->assertOk();

        $this->assertSame('bounced', $user->fresh()->email_status);
    }

    public function test_a_malformed_created_at_does_not_break_a_delivery_event(): void
    {
        User::factory()->create(['email' => 'member@example.com']);

        $this->postWebhook($this->event('email.delivered', ['to' => ['member@example.com']], createdAt: 'not-a-date'))->assertOk();
    }

    public function test_any_of_several_v1_signatures_is_accepted(): void
    {
        $body = json_encode($this->event('domain.verified', []), JSON_THROW_ON_ERROR);
        $timestamp = time();
        $good = hash_hmac('sha256', $timestamp.'.'.$body, 'faivelo-test-key');

        $this->call('POST', route('webhooks.faivelo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Faivelo-Signature' => "t={$timestamp},v1={$good},v1=rotated-out",
        ], $body)->assertOk();
    }

    public function test_a_failed_sync_handler_releases_the_event_so_the_retry_is_processed(): void
    {
        $handler = \Mockery::mock(FaiveloWebhookHandler::class);
        $handler->shouldReceive('handle')->once()->andThrow(new \RuntimeException('db down'));
        $handler->shouldReceive('handle')->once()->andReturn('ok');
        $this->app->instance(FaiveloWebhookHandler::class, $handler);

        $event = $this->event('email.delivered', ['to' => ['member@example.com']], id: 'evt_retry');

        $this->postWebhook($event)->assertStatus(500);
        $this->postWebhook($event)->assertOk()->assertJson(['status' => 'processed']);
    }

    public function test_unknown_event_types_are_acknowledged(): void
    {
        $this->postWebhook($this->event('domain.verified', []))
            ->assertOk()->assertJson(['result' => 'Event ignored.']);
    }

    public function test_missing_or_invalid_signature_is_rejected(): void
    {
        $body = json_encode($this->event('email.bounced', ['to' => ['member@example.com']]), JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.faivelo'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);

        $this->call('POST', route('webhooks.faivelo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Faivelo-Signature' => 't='.time().',v1=forged',
        ], $body)->assertStatus(400);
    }

    public function test_a_stale_signature_is_rejected_even_when_correctly_signed(): void
    {
        $this->postWebhook($this->event('email.delivered', ['to' => ['member@example.com']]), timestamp: time() - 600)
            ->assertStatus(400);
    }

    public function test_redelivery_is_processed_once_even_after_a_cache_flush(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'receives_email' => true]);
        $event = $this->event('email.bounced', ['to' => ['member@example.com'], 'bounce' => ['type' => 'hard']], id: 'evt_dupe');

        $this->postWebhook($event)->assertOk()->assertJson(['status' => 'processed']);

        User::query()->whereKey($user->id)->update(['receives_email' => true]);
        Cache::flush();

        $this->postWebhook($event)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertTrue((bool) $user->fresh()->receives_email);
    }

    public function test_async_queue_only_dispatches_a_job(): void
    {
        Queue::fake();
        config()->set('queue.default', 'database');

        $event = $this->event('email.delivered', ['to' => ['member@example.com']], id: 'evt_async');

        $this->postWebhook($event)->assertOk()->assertJson(['status' => 'queued']);

        Queue::assertPushed(ProcessFaiveloWebhookEvent::class, fn (ProcessFaiveloWebhookEvent $job): bool => $job->eventData === $event);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function event(string $type, array $data, string $id = 'evt_1', string $createdAt = '2026-11-18T10:00:00Z'): array
    {
        return ['id' => $id, 'type' => $type, 'created_at' => $createdAt, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function postWebhook(array $event, ?int $timestamp = null): TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('services.faivelo.webhook_signing_key'));

        return $this->call('POST', route('webhooks.faivelo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Faivelo-Signature' => "t={$timestamp},v1={$signature}",
        ], $body);
    }
}
