<?php

use App\Notifications\BackupFailedNotification;
use App\Notifications\SearchIndexHealthCheckFailedNotification;
use Filagora\Core\Settings\SettingsRegistry;
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

// Digests of new posts in followed threads: every morning, and Mondays for the weekly ones.
Schedule::command('filagora:forums:send-digest daily')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('filagora:forums:send-digest weekly')->weeklyOn(1, '08:00')->withoutOverlapping();

// Publish pages and menus whose scheduled time has arrived.
Schedule::command('filagora:cms:publish-due')->everyMinute()->withoutOverlapping();

// Uploaded files nobody attached to a post.
Schedule::command('filagora:forums:prune-attachments')->hourly()->withoutOverlapping();

Schedule::command('registration:prune-send-counters')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('filagora:push:prune-stale')->dailyAt('02:35')->withoutOverlapping();

// Compare each search index with what it should hold, and mail the people named in the setting.
if ((bool) config('search-ops.schedule.enabled', true)) {
    $searchHealth = Schedule::command('filagora:search:check-health')
        ->dailyAt((string) config('search-ops.schedule.daily_at', '03:15'))
        ->withoutOverlapping();

    $searchHealth->onSuccess(fn () => Log::info('Scheduled search health check completed successfully.'));

    $searchHealth->onFailure(function (): void {
        Log::error('Scheduled search health check found a problem.');

        foreach (explode(',', (string) app(SettingsRegistry::class)->get('search_health_check_alert_emails', '')) as $email) {
            $address = trim($email);

            if ($address !== '') {
                Notification::route('mail', $address)->notify(new SearchIndexHealthCheckFailedNotification(app()->environment(), (string) config('app.url')));
            }
        }
    });
}

// Re-scan active releases whose latest scan predates the current scanner, a few at a time, so a
// scanner update spreads over the day instead of arriving as one long burst.
if ((bool) config('plugin-ops.security_scan.sweep.enabled', true)) {
    Schedule::command(sprintf(
        'filagora:plugins:security-scan --status=active --stale --trigger=sweep --limit=%d',
        max(1, (int) config('plugin-ops.security_scan.sweep.batch_size', 4)),
    ))->everyThirtyMinutes()->withoutOverlapping();
}
