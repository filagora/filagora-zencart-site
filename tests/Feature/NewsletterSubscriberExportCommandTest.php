<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\NewsletterSubscriberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterSubscriberExportCommandTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_export_command_writes_only_sendable_non_suppressed_subscribers(): void
    {
        $sendable = $this->subscriber([
            'email' => 'sendable@example.test',
            'normalized_email' => 'sendable@example.test',
            'name' => 'Sendable User',
            'status' => NewsletterSubscriberStatus::Subscribed,
            'source' => 'profile',
            'subscribed_at' => now()->subDay(),
            'confirmed_at' => now()->subDay(),
        ]);
        $this->subscriber([
            'email' => 'pending@example.test',
            'normalized_email' => 'pending@example.test',
            'status' => NewsletterSubscriberStatus::Pending,
        ]);
        $this->subscriber([
            'email' => 'unsubscribed@example.test',
            'normalized_email' => 'unsubscribed@example.test',
            'status' => NewsletterSubscriberStatus::Unsubscribed,
        ]);
        $this->subscriber([
            'email' => 'erased-subscriber-1@anonymized.invalid',
            'normalized_email' => 'erased-subscriber-1@anonymized.invalid',
            'suppression_hash' => hash('sha256', 'erased@example.test'),
            'status' => NewsletterSubscriberStatus::Unsubscribed,
            'source' => 'dsar_erasure',
            'unsubscribe_reason' => 'dsar_erasure',
        ]);

        $path = sys_get_temp_dir().'/newsletter-export-'.uniqid('', true).'.csv';

        $this->artisan('newsletter:export-subscribers', ['path' => $path])
            ->expectsOutput("Exported 1 sendable newsletter subscriber(s) to {$path}.")
            ->assertSuccessful();

        $rows = $this->csvRows($path);

        $this->assertSame([
            'email',
            'name',
            'status',
            'source',
            'subscribed_at',
            'confirmed_at',
            'unsubscribe_url',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('sendable@example.test', $rows[1][0]);
        $this->assertSame('Sendable User', $rows[1][1]);
        $this->assertSame('subscribed', $rows[1][2]);
        $this->assertSame('profile', $rows[1][3]);
        $this->assertStringContainsString(route('newsletter.unsubscribe', $sendable, absolute: false), $rows[1][6]);
        $this->assertStringContainsString('signature=', $rows[1][6]);
    }

    public function test_export_command_creates_missing_destination_directory(): void
    {
        $this->subscriber([
            'email' => 'directory@example.test',
            'normalized_email' => 'directory@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);

        $path = sys_get_temp_dir().'/newsletter-export-'.uniqid('', true).'/subscribers.csv';

        $this->artisan('newsletter:export-subscribers', ['path' => $path])
            ->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertCount(2, $this->csvRows($path));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscriber(array $attributes): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create([
            'email' => $attributes['email'],
            'normalized_email' => $attributes['normalized_email'],
            'suppression_hash' => $attributes['suppression_hash'] ?? null,
            'name' => $attributes['name'] ?? null,
            'status' => $attributes['status'],
            'source' => $attributes['source'] ?? 'public_signup',
            'subscribed_at' => $attributes['subscribed_at'] ?? null,
            'confirmed_at' => $attributes['confirmed_at'] ?? null,
            'unsubscribe_reason' => $attributes['unsubscribe_reason'] ?? null,
        ]);
    }

    /**
     * @return list<list<string|null>>
     */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $this->assertIsResource($handle);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
