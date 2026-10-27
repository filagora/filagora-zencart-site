<?php

namespace App\Notifications;

use Filagora\Forums\Filament\Resources\PostReports\PostReportResource;
use Filagora\Forums\Models\PostReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Told to the forum support address when a member reports a post.
 */
class PostReportSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PostReport $report) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reporter = $this->report->reporter?->username ?: $this->report->reporter?->email ?: 'Unknown user';
        $reason = trim((string) $this->report->reason);

        return (new MailMessage)
            ->subject(__('notifications.post_report.subjects.submitted'))
            ->line(__('notifications.post_report.email.submitted.intro'))
            ->line(__('notifications.post_report.email.submitted.report_id', ['id' => $this->report->id]))
            ->line(__('notifications.post_report.email.submitted.thread_id', ['id' => $this->report->thread_id]))
            ->line(__('notifications.post_report.email.submitted.post_id', ['id' => $this->report->post_id]))
            ->line(__('notifications.post_report.email.submitted.reporter', ['reporter' => $reporter]))
            ->line(__('notifications.post_report.email.submitted.reason', ['reason' => mb_strlen($reason) > 400 ? mb_substr($reason, 0, 400).'...' : $reason]))
            ->action(__('notifications.post_report.email.submitted.action'), PostReportResource::getUrl('edit', ['record' => $this->report]));
    }
}
