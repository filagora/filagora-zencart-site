<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Everything the old site ran from its scheduler is still scheduled, at the same time, by the
 * package or host command that took it over.
 */
class ScheduleTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function jobs(): array
    {
        return [
            'daily digests' => ['filagora:forums:send-digest daily', '0 8 * * *'],
            'weekly digests' => ['filagora:forums:send-digest weekly', '0 8 * * 1'],
            'sitemap' => ['sitemap:generate', '0 3 * * *'],
            'newsletter queue' => ['newsletter:queue-scheduled', '* * * * *'],
            'scheduled pages' => ['filagora:cms:publish-due', '* * * * *'],
            'orphaned attachments' => ['filagora:forums:prune-attachments', '0 * * * *'],
            'webhook records' => ['webhooks:prune-events', '0 * * * *'],
            'registration counters' => ['registration:prune-send-counters', '30 2 * * *'],
            'stale push subscriptions' => ['filagora:push:prune-stale', '35 2 * * *'],
            'notification history' => ['notifications:prune', '40 2 * * *'],
            'trashed conversations' => ['filagora:messages:purge-trashed', '20 2 * * *'],
            'queue job records' => ['filament-jobs-monitor:prune', '50 2 * * *'],
            'failed jobs' => ['queue:prune-failed --hours=168', '55 2 * * *'],
            'database backup' => ['backup:run', '30 1 * * *'],
            'backup clean-up' => ['backup:clean', '45 2 * * *'],
            'search health' => ['filagora:search:check-health', '15 3 * * *'],
            'privacy retention' => ['filagora:compliance:prune', '15 2 * * *'],
        ];
    }

    /**
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('jobs')]
    public function test_the_job_is_scheduled(string $command, string $expression): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($scheduled): bool => str_contains((string) $scheduled->command, $command));

        $this->assertNotNull($event, "{$command} is not scheduled.");
        $this->assertSame($expression, $event->expression);
        // The compliance package schedules its own prune; the others are scheduled here.
        if (! str_starts_with($command, 'filagora:compliance:')) {
            $this->assertTrue($event->withoutOverlapping, "{$command} should not overlap itself.");
        }
    }

    public function test_the_plugin_security_sweep_runs_every_half_hour(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($scheduled): bool => str_contains((string) $scheduled->command, 'filagora:plugins:security-scan'));

        $this->assertNotNull($event);
        $this->assertSame('*/30 * * * *', $event->expression);
        $this->assertStringContainsString('--trigger=sweep', (string) $event->command);
    }
}
