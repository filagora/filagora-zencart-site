<?php

namespace App\Notifications;

use Filagora\Plugins\Models\PluginReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A plugin report, mailed to support when it is filed and to the reporter when staff review it, with
 * lara-forum's wording. Replaces the plugins package's own report mail (switched off in config).
 */
class PluginReportMailNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const SUBMITTED = 'submitted';

    public const STATUS_UPDATED = 'status_updated';

    public bool $deleteWhenMissingModels = true;

    public readonly string $kind;

    public function __construct(private readonly PluginReport $report, private readonly string $type)
    {
        $this->kind = $type === self::SUBMITTED ? 'report_submitted' : 'report_reviewed';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->type === self::SUBMITTED ? $this->submitted() : $this->statusUpdated();
    }

    private function submitted(): MailMessage
    {
        $reporter = $this->report->reporter?->username ?: $this->report->reporter?->email ?: 'Unknown user';
        $pluginName = $this->report->plugin?->name ?? 'Unknown plugin';
        $releaseVersion = $this->report->release?->version;
        $reason = trim((string) $this->report->reason);
        $reasonPreview = mb_strlen($reason) > 400 ? mb_substr($reason, 0, 400).'...' : $reason;

        $message = (new MailMessage)
            ->subject(__('notifications.plugin_report.subjects.submitted'))
            ->line(__('notifications.plugin_report.email.submitted.intro'))
            ->line(__('notifications.plugin_report.email.submitted.report_id', ['id' => $this->report->getKey()]))
            ->line(__('notifications.plugin_report.email.submitted.plugin_name', ['plugin' => $pluginName]));

        if ($releaseVersion !== null) {
            $message->line(__('notifications.plugin_report.email.submitted.release_version', ['version' => $releaseVersion]));
        }

        return $message
            ->line(__('notifications.plugin_report.email.submitted.reporter', ['reporter' => $reporter]))
            ->line(__('notifications.plugin_report.email.submitted.reason', ['reason' => $reasonPreview]))
            ->action(__('notifications.plugin_report.email.submitted.action'), route('filament.dashboard.resources.plugin-reports.index'));
    }

    private function statusUpdated(): MailMessage
    {
        $status = $this->report->status?->label() ?? ucfirst((string) $this->report->status);
        $note = trim((string) ($this->report->resolution_note ?? ''));

        $message = (new MailMessage)
            ->subject(__('notifications.plugin_report.subjects.status_updated'))
            ->line(__('notifications.plugin_report.email.status_updated.intro'))
            ->line(__('notifications.plugin_report.email.status_updated.report_id', ['id' => $this->report->getKey()]))
            ->line(__('notifications.plugin_report.email.status_updated.new_status', ['status' => $status]));

        if ($note !== '') {
            $message->line(__('notifications.plugin_report.email.status_updated.moderator_note', ['note' => $note]));
        }

        return $message->action(__('notifications.plugin_report.email.status_updated.action'), route('plugins.show', $this->report->plugin));
    }
}
