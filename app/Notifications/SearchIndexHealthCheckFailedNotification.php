<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SearchIndexHealthCheckFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $environment,
        private readonly string $appUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.search_health_check.subjects.failed'))
            ->line(__('notifications.search_health_check.email.failed.intro'))
            ->line(__('notifications.search_health_check.email.failed.environment', ['environment' => $this->environment]))
            ->line(__('notifications.search_health_check.email.failed.app_url', ['url' => $this->appUrl]))
            ->line(__('notifications.search_health_check.email.failed.action'));
    }
}
