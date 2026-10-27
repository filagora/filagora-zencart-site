<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BackupScheduleLoggingTest extends TestCase
{
    public function test_successful_backup_run_is_logged_to_the_backup_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('backup')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('Scheduled backup:run completed successfully.');

        $this->scheduledEvent('backup:run')->finish($this->app, 0);
    }

    public function test_failed_backup_run_is_logged_to_the_backup_channel(): void
    {
        config(['backup-ops.alerts.emails' => []]);

        Log::shouldReceive('channel')->once()->with('backup')->andReturnSelf();
        Log::shouldReceive('error')->once()->with('Scheduled backup:run failed.');

        $this->scheduledEvent('backup:run')->finish($this->app, 1);
    }

    public function test_successful_backup_clean_is_logged_to_the_backup_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('backup')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('Scheduled backup:clean completed successfully.');

        $this->scheduledEvent('backup:clean')->finish($this->app, 0);
    }

    public function test_failed_backup_clean_is_logged_to_the_backup_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('backup')->andReturnSelf();
        Log::shouldReceive('error')->once()->with('Scheduled backup:clean failed.');

        $this->scheduledEvent('backup:clean')->finish($this->app, 1);
    }

    public function test_it_follows_the_configured_backup_log_channel(): void
    {
        config(['backup.log_channel' => 'stack']);

        Log::shouldReceive('channel')->twice()->with('stack')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        $this->scheduledEvent('backup:run')->finish($this->app, 0);
        $this->scheduledEvent('backup:clean')->finish($this->app, 0);
    }

    public function test_it_falls_back_to_the_default_channel_when_backup_logging_is_disabled(): void
    {
        config(['backup.log_channel' => false]);

        Log::shouldReceive('channel')->twice()->with(null)->andReturnSelf();
        Log::shouldReceive('info')->twice();

        $this->scheduledEvent('backup:run')->finish($this->app, 0);
        $this->scheduledEvent('backup:clean')->finish($this->app, 0);
    }

    private function scheduledEvent(string $command): Event
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, $command));

        $this->assertNotNull($event, "The {$command} command is not scheduled.");

        return $event;
    }
}
