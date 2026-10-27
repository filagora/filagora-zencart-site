<?php

namespace Tests\Feature;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class QueueMonitorPruneScheduleTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_the_job_monitor_prune_runs_once_a_day_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn (Event $event): bool => str_contains((string) $event->command, 'filament-jobs-monitor:prune'),
        );

        $this->assertInstanceOf(Event::class, $event, 'filament-jobs-monitor:prune is not scheduled.');
        $this->assertSame('50 2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_failed_jobs_are_pruned_daily_with_a_seven_day_retention(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn (Event $event): bool => str_contains((string) $event->command, 'queue:prune-failed'),
        );

        $this->assertInstanceOf(Event::class, $event, 'queue:prune-failed is not scheduled.');
        $this->assertStringContainsString('--hours=168', (string) $event->command);
        $this->assertSame('55 2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_prune_removes_rows_past_the_retention_window_and_keeps_newer_ones(): void
    {
        config(['filament-jobs-monitor.pruning.enabled' => true]);
        config(['filament-jobs-monitor.pruning.retention_days' => 7]);

        $stale = $this->monitorRecord(createdDaysAgo: 8);
        $staleFailed = $this->monitorRecord(createdDaysAgo: 30, failed: true);
        $fresh = $this->monitorRecord(createdDaysAgo: 2);

        $this->artisan('filament-jobs-monitor:prune')->assertSuccessful();

        $this->assertDatabaseMissing('queue_monitors', ['id' => $stale->id]);
        $this->assertDatabaseMissing('queue_monitors', ['id' => $staleFailed->id]);
        $this->assertDatabaseHas('queue_monitors', ['id' => $fresh->id]);
    }

    private function monitorRecord(int $createdDaysAgo, bool $failed = false): QueueMonitor
    {
        $record = QueueMonitor::query()->create([
            'job_id' => 'job-'.fake()->unique()->numerify('######'),
            'name' => 'App\\Jobs\\ExampleJob',
            'queue' => 'default',
            'started_at' => now()->subDays($createdDaysAgo),
            'finished_at' => now()->subDays($createdDaysAgo),
            'failed' => $failed,
        ]);

        DB::table('queue_monitors')
            ->where('id', $record->id)
            ->update(['created_at' => now()->subDays($createdDaysAgo)]);

        return $record->refresh();
    }
}
