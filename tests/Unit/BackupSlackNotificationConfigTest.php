<?php

namespace Tests\Unit;

use Illuminate\Notifications\Messages\SlackMessage;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Tests\TestCase;

class BackupSlackNotificationConfigTest extends TestCase
{
    public function test_slack_notification_channel_package_is_installed(): void
    {
        $this->assertTrue(class_exists(SlackMessage::class));
    }

    public function test_completed_backups_notify_slack_when_a_webhook_url_is_configured(): void
    {
        $notifications = $this->backupNotificationChannels('https://hooks.slack.com/services/T00000/B00000/xxxxxxxx');

        $this->assertSame(['slack'], $notifications[BackupWasSuccessfulNotification::class]);
    }

    /**
     * Posting to Slack is opt-in, and leaving it off has to stay harmless.
     *
     * spatie/laravel-backup builds the legacy Illuminate\Notifications\Messages\SlackMessage,
     * which only laravel/slack-notification-channel's webhook channel accepts. That channel is
     * chosen from the notifiable's route, so with no webhook URL the notification reaches the
     * Slack Web API channel instead and dies with a TypeError -- failing the backup command
     * after the dump has already been uploaded, and tripping the scheduler's onFailure() alert.
     */
    public function test_completed_backups_do_not_notify_slack_when_no_webhook_url_is_configured(): void
    {
        $notifications = $this->backupNotificationChannels(null);

        $this->assertSame([], $notifications[BackupWasSuccessfulNotification::class]);
    }

    /**
     * A value the router will not recognise as a webhook URL reaches the Web API channel just
     * like an empty one does, so it has to leave the channel off as well.
     */
    public function test_completed_backups_do_not_notify_slack_for_a_webhook_url_without_a_scheme(): void
    {
        $notifications = $this->backupNotificationChannels('hooks.slack.com/services/T00000/B00000/xxxxxxxx');

        $this->assertSame([], $notifications[BackupWasSuccessfulNotification::class]);
    }

    public function test_backup_failures_still_go_through_the_apps_own_email_alert_not_the_package_channels(): void
    {
        $notifications = config('backup.notifications.notifications');

        $this->assertSame([], $notifications[BackupHasFailedNotification::class]);
    }

    public function test_slack_webhook_url_is_driven_by_env(): void
    {
        $this->assertSame(
            (string) env('BACKUP_SLACK_WEBHOOK_URL', ''),
            config('backup.notifications.slack.webhook_url')
        );
    }

    /**
     * The channel list is resolved from env when the config file is loaded, so it cannot be
     * exercised through config()->set(). Re-read the file with the env var swapped instead.
     *
     * env() reads through Illuminate\Support\Env's cached Dotenv repository, which includes a
     * PutenvAdapter backed by getenv() — a process-level store independent of the $_SERVER/$_ENV
     * superglobals. When a real BACKUP_SLACK_WEBHOOK_URL is set in .env, Dotenv's own boot-time
     * loading calls putenv() for it, so clearing only $_SERVER/$_ENV leaves getenv() (and thus
     * env()) still returning the real value. All three layers have to be swapped and restored.
     *
     * @return array<class-string, list<string>>
     */
    private function backupNotificationChannels(?string $webhookUrl): array
    {
        $key = 'BACKUP_SLACK_WEBHOOK_URL';
        $previousServer = $_SERVER[$key] ?? null;
        $previousEnv = $_ENV[$key] ?? null;
        $previousGetenv = getenv($key);

        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);

        if ($webhookUrl !== null) {
            $_SERVER[$key] = $webhookUrl;
            $_ENV[$key] = $webhookUrl;
            putenv("{$key}={$webhookUrl}");
        }

        try {
            $config = require base_path('config/backup.php');
        } finally {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);

            if ($previousServer !== null) {
                $_SERVER[$key] = $previousServer;
            }
            if ($previousEnv !== null) {
                $_ENV[$key] = $previousEnv;
            }
            if ($previousGetenv !== false) {
                putenv("{$key}={$previousGetenv}");
            }
        }

        return $config['notifications']['notifications'];
    }
}
