<?php

namespace Tests\Feature;

use App\Jobs\ProcessMailgunWebhook;
use App\Models\Newsletter;
use App\Models\NewsletterEvent;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use App\Models\WebhookEvent;
use App\NewsletterEventType;
use App\NewsletterSendStatus;
use App\NewsletterStatus;
use App\NewsletterSubscriberStatus;
use App\Services\Mailgun\MailgunWebhookHandler;
use App\WebhookProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class MailgunWebhookControllerTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_sync_queue_processes_mailgun_webhook_once(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $eventData = [
            'event' => 'accepted',
            'recipient' => 'member@example.com',
        ];

        $handler = Mockery::mock(MailgunWebhookHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with($eventData)
            ->andReturn('Accepted event acknowledged.');
        $this->app->instance(MailgunWebhookHandler::class, $handler);

        $response = $this->postJson(route('webhooks.mailgun'), $this->validPayload($eventData));

        $response->assertOk()->assertJson([
            'status' => 'processed',
            'result' => 'Accepted event acknowledged.',
        ]);
    }

    public function test_async_queue_only_dispatches_mailgun_webhook_job(): void
    {
        Queue::fake();

        config()->set('queue.default', 'database');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $eventData = [
            'event' => 'accepted',
            'recipient' => 'member@example.com',
        ];

        $handler = Mockery::mock(MailgunWebhookHandler::class);
        $handler->shouldNotReceive('handle');
        $this->app->instance(MailgunWebhookHandler::class, $handler);

        $response = $this->postJson(route('webhooks.mailgun'), $this->validPayload($eventData));

        $response->assertOk()->assertJson([
            'status' => 'queued',
            'result' => null,
        ]);

        Queue::assertPushed(ProcessMailgunWebhook::class, function (ProcessMailgunWebhook $job) use ($eventData): bool {
            return $job->eventData === $eventData;
        });
    }

    public function test_replayed_signature_token_is_rejected(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $eventData = [
            'event' => 'accepted',
            'recipient' => 'member@example.com',
        ];

        $handler = Mockery::mock(MailgunWebhookHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->andReturn('Accepted event acknowledged.');
        $this->app->instance(MailgunWebhookHandler::class, $handler);

        $payload = $this->validPayload($eventData, 'mailgun-replayed-token');

        $this->postJson(route('webhooks.mailgun'), $payload)->assertOk();
        $this->postJson(route('webhooks.mailgun'), $payload)->assertStatus(406);
    }

    /**
     * Deploys run `php artisan optimize:clear`, which flushes the cache store outright.
     * The replay guard has to survive that, or a webhook redelivered around a deploy is
     * processed a second time.
     */
    public function test_clearing_the_cache_does_not_reintroduce_duplicate_processing(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $eventData = [
            'event' => 'accepted',
            'recipient' => 'member@example.com',
        ];

        $handler = Mockery::mock(MailgunWebhookHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->andReturn('Accepted event acknowledged.');
        $this->app->instance(MailgunWebhookHandler::class, $handler);

        $payload = $this->validPayload($eventData, 'mailgun-deploy-token');

        $this->postJson(route('webhooks.mailgun'), $payload)->assertOk();

        Cache::flush();

        $this->postJson(route('webhooks.mailgun'), $payload)->assertStatus(406);

        $this->assertSame(1, WebhookEvent::query()
            ->where('provider', WebhookProvider::Mailgun)
            ->where('event_key', 'mailgun-deploy-token')
            ->count());
    }

    public function test_mailgun_delivered_event_updates_newsletter_send_and_records_event(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $send = $this->newsletterSend();

        $response = $this->postJson(route('webhooks.mailgun'), $this->validPayload([
            'event' => 'delivered',
            'recipient' => $send->email,
            'timestamp' => now()->timestamp,
            'user-variables' => [
                'newsletter_send_id' => $send->id,
            ],
            'message' => [
                'headers' => [
                    'message-id' => '<mailgun-message-1@example.test>',
                ],
            ],
        ]));

        $response->assertOk()->assertJson([
            'status' => 'processed',
            'result' => 'Newsletter event recorded.',
        ]);

        $send->refresh();

        $this->assertSame(NewsletterSendStatus::Sent, $send->status);
        $this->assertSame('mailgun-message-1@example.test', $send->provider_message_id);
        $this->assertSame(NewsletterEventType::Delivered, NewsletterEvent::query()->firstOrFail()->type);
    }

    public function test_mailgun_permanent_bounce_suppresses_newsletter_subscriber(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $send = $this->newsletterSend();

        $this->postJson(route('webhooks.mailgun'), $this->validPayload([
            'event' => 'failed',
            'severity' => 'permanent',
            'reason' => 'suppress-bounce',
            'recipient' => $send->email,
            'user-variables' => [
                'newsletter_send_id' => $send->id,
            ],
            'delivery-status' => [
                'message' => 'Mailbox does not exist.',
            ],
        ]))->assertOk();

        $send->refresh();
        $subscriber = $send->subscriber()->firstOrFail();

        $this->assertSame(NewsletterSendStatus::Failed, $send->status);
        $this->assertSame('Mailbox does not exist.', $send->error);
        $this->assertSame(NewsletterSubscriberStatus::Bounced, $subscriber->status);
        $this->assertNotNull($subscriber->bounced_at);
        $this->assertSame(NewsletterEventType::Bounced, NewsletterEvent::query()->firstOrFail()->type);
    }

    public function test_mailgun_complaint_suppresses_newsletter_subscriber(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $send = $this->newsletterSend();

        $this->postJson(route('webhooks.mailgun'), $this->validPayload([
            'event' => 'complained',
            'recipient' => $send->email,
            'user-variables' => [
                'newsletter_send_id' => $send->id,
            ],
        ]))->assertOk();

        $send->refresh();
        $subscriber = $send->subscriber()->firstOrFail();

        $this->assertSame(NewsletterSendStatus::Failed, $send->status);
        $this->assertSame(NewsletterSubscriberStatus::Complained, $subscriber->status);
        $this->assertNotNull($subscriber->complained_at);
        $this->assertSame(NewsletterEventType::Complained, NewsletterEvent::query()->firstOrFail()->type);
    }

    public function test_mailgun_unsubscribe_event_unsubscribes_newsletter_subscriber(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailgun.webhook_signing_key', 'mailgun-test-key');

        $send = $this->newsletterSend();

        $this->postJson(route('webhooks.mailgun'), $this->validPayload([
            'event' => 'unsubscribed',
            'recipient' => $send->email,
            'user-variables' => [
                'newsletter_send_id' => $send->id,
            ],
        ]))->assertOk();

        $subscriber = $send->subscriber()->firstOrFail();

        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertSame('mailgun_unsubscribe', $subscriber->unsubscribe_reason);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertSame(NewsletterEventType::Unsubscribed, NewsletterEvent::query()->firstOrFail()->type);
    }

    private function validPayload(array $eventData, ?string $token = null): array
    {
        $timestamp = (string) now()->timestamp;
        $token ??= 'mailgun-token-'.bin2hex(random_bytes(8));

        return [
            'signature' => [
                'timestamp' => $timestamp,
                'token' => $token,
                'signature' => hash_hmac(
                    'sha256',
                    $timestamp.$token,
                    (string) config('services.mailgun.webhook_signing_key')
                ),
            ],
            'event-data' => $eventData,
        ];
    }

    private function newsletterSend(): NewsletterSend
    {
        $newsletter = Newsletter::query()->create([
            'title' => 'Mailgun Event Newsletter',
            'subject' => 'Mailgun event newsletter',
            'body_html' => '<p>Body</p>',
            'body_text' => 'Body',
            'status' => NewsletterStatus::Sending,
        ]);

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'newsletter-recipient@example.test',
            'normalized_email' => 'newsletter-recipient@example.test',
            'name' => 'Recipient',
            'status' => NewsletterSubscriberStatus::Subscribed,
            'source' => 'public_signup',
            'subscribed_at' => now(),
            'confirmed_at' => now(),
        ]);

        return NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Sending,
            'queued_at' => now(),
        ]);
    }
}
