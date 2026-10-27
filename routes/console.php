<?php

use App\Notifications\BackupFailedNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sitemap:generate')->dailyAt('03:00')->withoutOverlapping();

Schedule::command('notifications:prune')->dailyAt('02:40')->withoutOverlapping();

Schedule::command('filagora:messages:purge-trashed')->dailyAt('02:20')->withoutOverlapping();

Schedule::command('newsletter:queue-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('webhooks:prune-events')->hourly()->withoutOverlapping();

if ((bool) config('backup-ops.schedule.enabled', true)) {
    $backupEvent = Schedule::command('backup:run --only-db')
        ->dailyAt((string) config('backup-ops.schedule.daily_at', '01:30'))
        ->withoutOverlapping();

    /**
     * Keep the scheduler's own backup messages with spatie's backup activity
     * instead of the default stack. Mirrors backup.log_channel, and falls back
     * to the default channel when that is null (spatie's "use the default"
     * value) or false (backup logging disabled).
     */
    $backupLogChannel = static function (): ?string {
        $channel = config('backup.log_channel');

        return is_string($channel) && $channel !== '' ? $channel : null;
    };

    $backupEvent->onSuccess(function () use ($backupLogChannel): void {
        Log::channel($backupLogChannel())->info('Scheduled backup:run completed successfully.');
    });

    $backupEvent->onFailure(function () use ($backupLogChannel): void {
        Log::channel($backupLogChannel())->error('Scheduled backup:run failed.');

        $emails = config('backup-ops.alerts.emails', []);
        if (! is_array($emails) || $emails === []) {
            return;
        }

        foreach ($emails as $email) {
            $address = trim((string) $email);
            if ($address === '') {
                continue;
            }

            Notification::route('mail', $address)
                ->notify(new BackupFailedNotification(
                    app()->environment(),
                    (string) config('app.url')
                ));
        }
    });

    $backupCleanEvent = Schedule::command('backup:clean')
        ->dailyAt((string) config('backup-ops.schedule.clean_at', '02:45'))
        ->withoutOverlapping();

    $backupCleanEvent->onSuccess(function () use ($backupLogChannel): void {
        Log::channel($backupLogChannel())->info('Scheduled backup:clean completed successfully.');
    });

    $backupCleanEvent->onFailure(function () use ($backupLogChannel): void {
        Log::channel($backupLogChannel())->error('Scheduled backup:clean failed.');
    });
}

// The queue jobs screen keeps a row per job; drop those older than a week.
Schedule::command('filament-jobs-monitor:prune')->dailyAt('02:50')->withoutOverlapping();

// Drop resolved or abandoned entries from the failed_jobs table after 7 days.
Schedule::command('queue:prune-failed --hours=168')->dailyAt('02:55')->withoutOverlapping();
