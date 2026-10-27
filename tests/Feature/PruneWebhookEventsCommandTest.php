<?php

namespace Tests\Feature;

use App\Models\WebhookEvent;
use App\WebhookProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PruneWebhookEventsCommandTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_it_prunes_only_expired_webhook_event_records(): void
    {
        WebhookEvent::claim(WebhookProvider::Mailgun, 'expired-token', now()->subMinute());
        WebhookEvent::claim(WebhookProvider::Mailtrap, 'live-event', now()->addHours(24));

        $this->artisan('webhooks:prune-events')
            ->expectsOutputToContain('Pruned 1 expired webhook event record(s).')
            ->assertExitCode(0);

        $this->assertSame(['live-event'], WebhookEvent::query()->pluck('event_key')->all());
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        WebhookEvent::claim(WebhookProvider::Mailgun, 'expired-token', now()->subMinute());

        $this->artisan('webhooks:prune-events', ['--dry-run' => true])
            ->expectsOutputToContain('Would prune 1 expired webhook event record(s).')
            ->assertExitCode(0);

        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_the_same_event_key_can_be_claimed_again_once_pruned(): void
    {
        $this->assertTrue(WebhookEvent::claim(WebhookProvider::Mailtrap, 'evt-1', now()->subMinute()));
        $this->assertFalse(WebhookEvent::claim(WebhookProvider::Mailtrap, 'evt-1', now()->addHours(24)));

        $this->artisan('webhooks:prune-events')->assertExitCode(0);

        $this->assertTrue(WebhookEvent::claim(WebhookProvider::Mailtrap, 'evt-1', now()->addHours(24)));
    }

    public function test_the_same_event_key_is_tracked_separately_per_provider(): void
    {
        $this->assertTrue(WebhookEvent::claim(WebhookProvider::Mailgun, 'shared-key', now()->addHours(24)));
        $this->assertTrue(WebhookEvent::claim(WebhookProvider::Mailtrap, 'shared-key', now()->addHours(24)));

        $this->assertSame(2, WebhookEvent::query()->count());
    }
}
