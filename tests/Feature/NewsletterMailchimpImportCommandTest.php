<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\NewsletterSubscriberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterMailchimpImportCommandTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_import_command_imports_mailchimp_subscribers_idempotently(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mailchimp-newsletter-');
        $this->assertIsString($path);

        file_put_contents($path, implode("\n", [
            'Email Address,First Name,Last Name,Email Marketing Status,Opt-In Time',
            'Alice@example.test,Alice,Example,subscribed,2026-06-01 10:00:00',
            'bob@example.test,Bob,Example,unsubscribed,2026-06-02 11:00:00',
            'cleaned@example.test,Cleaned,Example,cleaned,2026-06-03 12:00:00',
            'not-an-email,Invalid,Example,subscribed,2026-06-04 13:00:00',
        ]));

        $this->artisan('newsletter:import-mailchimp', ['path' => $path])
            ->assertSuccessful();

        $this->assertSame(3, NewsletterSubscriber::query()->count());

        $this->assertSame(
            NewsletterSubscriberStatus::Subscribed,
            NewsletterSubscriber::query()->where('normalized_email', 'alice@example.test')->first()?->status
        );

        $this->assertSame(
            NewsletterSubscriberStatus::Unsubscribed,
            NewsletterSubscriber::query()->where('normalized_email', 'bob@example.test')->first()?->status
        );

        $this->assertSame(
            NewsletterSubscriberStatus::Bounced,
            NewsletterSubscriber::query()->where('normalized_email', 'cleaned@example.test')->first()?->status
        );

        $this->artisan('newsletter:import-mailchimp', ['path' => $path])
            ->assertSuccessful();

        $this->assertSame(3, NewsletterSubscriber::query()->count());
    }

    public function test_import_command_dry_run_does_not_write_subscribers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mailchimp-newsletter-dry-run-');
        $this->assertIsString($path);

        file_put_contents($path, implode("\n", [
            'Email Address,Email Marketing Status',
            'dryrun@example.test,subscribed',
        ]));

        $this->artisan('newsletter:import-mailchimp', [
            'path' => $path,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    public function test_import_command_does_not_reactivate_erased_suppression_records(): void
    {
        NewsletterSubscriber::query()->create([
            'email' => 'erased-subscriber-1@anonymized.invalid',
            'normalized_email' => 'erased-subscriber-1@anonymized.invalid',
            'suppression_hash' => hash('sha256', 'erased@example.test'),
            'status' => NewsletterSubscriberStatus::Unsubscribed,
            'source' => 'dsar_erasure',
            'unsubscribed_at' => now()->subDay(),
            'unsubscribe_reason' => 'dsar_erasure',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'mailchimp-newsletter-suppressed-');
        $this->assertIsString($path);

        file_put_contents($path, implode("\n", [
            'Email Address,First Name,Last Name,Email Marketing Status,Opt-In Time',
            'erased@example.test,Erased,User,subscribed,2026-06-01 10:00:00',
        ]));

        $this->artisan('newsletter:import-mailchimp', ['path' => $path])
            ->assertSuccessful()
            ->expectsTable(
                ['Metric', 'Count'],
                [
                    ['seen', '1'],
                    ['imported', '0'],
                    ['updated', '0'],
                    ['invalid', '0'],
                    ['suppressed', '1'],
                ]
            );

        $subscriber = NewsletterSubscriber::query()->firstOrFail();

        $this->assertSame(1, NewsletterSubscriber::query()->count());
        $this->assertSame('erased-subscriber-1@anonymized.invalid', $subscriber->email);
        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertSame('dsar_erasure', $subscriber->unsubscribe_reason);
    }
}
