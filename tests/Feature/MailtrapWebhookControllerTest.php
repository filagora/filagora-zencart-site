<?php

namespace Tests\Feature;

use App\Jobs\ProcessMailtrapWebhookEvent;
use App\Models\Newsletter;
use App\Models\NewsletterEvent;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use App\Models\WebhookEvent;
use App\NewsletterEventType;
use App\NewsletterSendStatus;
use App\NewsletterStatus;
use App\NewsletterSubscriberStatus;
use App\Services\Mailtrap\MailtrapWebhookHandler;
use App\WebhookProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class MailtrapWebhookControllerTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_sync_queue_processes_mailtrap_webhook_events_once(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $eventData = [
            'event' => 'open',
            'email' => 'member@example.com',
            'event_id' => 'evt-1',
        ];

        $handler = Mockery::mock(MailtrapWebhookHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with($eventData)
            ->andReturn('Tracking event logged.');
        $this->app->instance(MailtrapWebhookHandler::class, $handler);

        $response = $this->postWebhook([$eventData]);

        $response->assertOk()->assertJson([
            'status' => 'processed',
            'result' => ['Tracking event logged.'],
        ]);
    }

    public function test_async_queue_only_dispatches_mailtrap_webhook_jobs(): void
    {
        Queue::fake();

        config()->set('queue.default', 'database');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $eventData = [
            'event' => 'open',
            'email' => 'member@example.com',
            'event_id' => 'evt-2',
        ];

        $handler = Mockery::mock(MailtrapWebhookHandler::class);
        $handler->shouldNotReceive('handle');
        $this->app->instance(MailtrapWebhookHandler::class, $handler);

        $response = $this->postWebhook([$eventData]);

        $response->assertOk()->assertJson([
            'status' => 'queued',
            'result' => null,
        ]);

        Queue::assertPushed(ProcessMailtrapWebhookEvent::class, function (ProcessMailtrapWebhookEvent $job) use ($eventData): bool {
            return $job->eventData === $eventData;
        });
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $response = $this->postJson(route('webhooks.mailtrap'), [
            'events' => [['event' => 'open', 'email' => 'member@example.com']],
        ], [
            'Mailtrap-Signature' => 'not-a-valid-signature',
        ]);

        $response->assertStatus(406);
    }

    public function test_duplicate_event_id_is_processed_only_once(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $eventData = [
            'event' => 'open',
            'email' => 'member@example.com',
            'event_id' => 'evt-dedupe',
        ];

        $this->postWebhook([$eventData]);
        $response = $this->postWebhook([$eventData]);

        $response->assertOk()->assertJson([
            'status' => 'processed',
            'result' => [],
        ]);
    }

    /**
     * Deploys run `php artisan optimize:clear`, which flushes the cache store outright.
     * Event de-duplication has to survive that, or a redelivered event is handled twice.
     */
    public function test_clearing_the_cache_does_not_reintroduce_duplicate_processing(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $eventData = [
            'event' => 'open',
            'email' => 'member@example.com',
            'event_id' => 'evt-deploy-dedupe',
        ];

        $handler = Mockery::mock(MailtrapWebhookHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with($eventData)
            ->andReturn('Tracking event logged.');
        $this->app->instance(MailtrapWebhookHandler::class, $handler);

        $this->postWebhook([$eventData])->assertOk();

        Cache::flush();

        $this->postWebhook([$eventData])->assertOk()->assertJson([
            'status' => 'processed',
            'result' => [],
        ]);

        $this->assertSame(1, WebhookEvent::query()
            ->where('provider', WebhookProvider::Mailtrap)
            ->where('event_key', 'evt-deploy-dedupe')
            ->count());
    }

    public function test_mailtrap_delivery_event_updates_newsletter_send_and_records_event(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $send = $this->newsletterSend();

        $response = $this->postWebhook([[
            'event' => 'delivery',
            'email' => $send->email,
            'timestamp' => now()->timestamp,
            'event_id' => 'evt-delivery',
            'message_id' => 'mailtrap-message-1@example.test',
            'custom_variables' => [
                'newsletter_send_id' => (string) $send->id,
            ],
        ]]);

        $response->assertOk()->assertJson([
            'status' => 'processed',
            'result' => ['Newsletter event recorded.'],
        ]);

        $send->refresh();

        $this->assertSame(NewsletterSendStatus::Sent, $send->status);
        $this->assertSame('mailtrap-message-1@example.test', $send->provider_message_id);
        $this->assertSame(NewsletterEventType::Delivered, NewsletterEvent::query()->firstOrFail()->type);
    }

    public function test_mailtrap_bounce_suppresses_newsletter_subscriber(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $send = $this->newsletterSend();

        $this->postWebhook([[
            'event' => 'bounce',
            'email' => $send->email,
            'event_id' => 'evt-bounce',
            'response' => 'Mailbox does not exist.',
            'custom_variables' => [
                'newsletter_send_id' => (string) $send->id,
            ],
        ]])->assertOk();

        $send->refresh();
        $subscriber = $send->subscriber()->firstOrFail();

        $this->assertSame(NewsletterSendStatus::Failed, $send->status);
        $this->assertSame('Mailbox does not exist.', $send->error);
        $this->assertSame(NewsletterSubscriberStatus::Bounced, $subscriber->status);
        $this->assertNotNull($subscriber->bounced_at);
        $this->assertSame(NewsletterEventType::Bounced, NewsletterEvent::query()->firstOrFail()->type);
    }

    public function test_mailtrap_spam_complaint_suppresses_newsletter_subscriber(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $send = $this->newsletterSend();

        $this->postWebhook([[
            'event' => 'spam',
            'email' => $send->email,
            'event_id' => 'evt-spam',
            'custom_variables' => [
                'newsletter_send_id' => (string) $send->id,
            ],
        ]])->assertOk();

        $send->refresh();
        $subscriber = $send->subscriber()->firstOrFail();

        $this->assertSame(NewsletterSendStatus::Failed, $send->status);
        $this->assertSame(NewsletterSubscriberStatus::Complained, $subscriber->status);
        $this->assertNotNull($subscriber->complained_at);
        $this->assertSame(NewsletterEventType::Complained, NewsletterEvent::query()->firstOrFail()->type);
    }

    public function test_mailtrap_unsubscribe_event_unsubscribes_newsletter_subscriber(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('services.mailtrap.webhook_signing_key', 'mailtrap-test-key');

        $send = $this->newsletterSend();

        $this->postWebhook([[
            'event' => 'unsubscribe',
            'email' => $send->email,
            'event_id' => 'evt-unsub',
            'custom_variables' => [
                'newsletter_send_id' => (string) $send->id,
            ],
        ]])->assertOk();

        $subscriber = $send->subscriber()->firstOrFail();

        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertSame('mailtrap_unsubscribe', $subscriber->unsubscribe_reason);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertSame(NewsletterEventType::Unsubscribed, NewsletterEvent::query()->firstOrFail()->type);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function postWebhook(array $events): TestResponse
    {
        $body = json_encode(['events' => $events], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, (string) config('services.mailtrap.webhook_signing_key'));

        return $this->call('POST', route('webhooks.mailtrap'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Mailtrap-Signature' => $signature,
        ], $body);
    }

    private function newsletterSend(): NewsletterSend
    {
        $newsletter = Newsletter::query()->create([
            'title' => 'Mailtrap Event Newsletter',
            'subject' => 'Mailtrap event newsletter',
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
