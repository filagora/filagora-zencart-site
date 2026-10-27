<?php

namespace App\Notifications;

use App\Models\BugReport;
use App\Models\User;
use App\Notifications\Channels\CoalescingDatabaseChannel;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Notifications\StandardCategories;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A bug report on a thread the member follows was opened or changed. It follows the same rules as a
 * reply to the thread: mail only for followers who take instant mail and are not suppressed, and a
 * bell row for everyone who has the bell on for thread updates.
 */
class BugReportUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $changes  What changed, one line each.
     */
    public function __construct(
        private readonly BugReport $bugReport,
        private readonly array $changes,
        private readonly int|string|null $actorId = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $preferences = app(Preferences::class);
        $channels = [];

        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        if ($preferences->mode($notifiable, StandardCategories::THREAD_UPDATES_DIGEST_FREQUENCY) === 'instant'
            && $preferences->canDeliver($notifiable, StandardCategories::THREAD_UPDATES_INSTANT)) {
            $channels[] = 'mail';
        }

        if ($preferences->allows($notifiable, StandardCategories::THREAD_UPDATES_INSTANT, NotificationChannel::IN_APP)) {
            $channels[] = CoalescingDatabaseChannel::class;
        }

        return $channels;
    }

    /**
     * @return array{title: string, body: string, url: string, category: string, actor_id: int|string|null, coalesce_key: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => (string) $this->bugReport->thread->title,
            'body' => implode('; ', $this->changes),
            'url' => $this->url(),
            'category' => StandardCategories::THREAD_UPDATES_INSTANT,
            'actor_id' => $this->actorId,
            'coalesce_key' => 'bug-report:'.$this->bugReport->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('notifications.bug_report.subject', ['thread' => $this->bugReport->thread->title]))
            ->markdown('emails.notifications.bug-report-updated', [
                'threadTitle' => $this->bugReport->thread->title,
                'status' => $this->bugReport->status->label(),
                'changes' => $this->changes,
                'bugReportUrl' => $this->url(),
                'manageSubscriptionsUrl' => route('profile.show', ['tab' => 'subscriptions']),
                'managePreferencesUrl' => route('profile.show', ['tab' => 'settings']),
                'unsubscribeUrl' => $notifiable instanceof User
                    ? app(Unsubscribe::class)->url($notifiable, StandardCategories::THREAD_UPDATES_INSTANT)
                    : route('profile.show', ['tab' => 'settings']),
            ]);

        return $notifiable instanceof User
            ? app(Unsubscribe::class)->decorate($message, $notifiable, StandardCategories::THREAD_UPDATES_INSTANT)
            : $message;
    }

    private function url(): string
    {
        return route('threads.show', $this->bugReport->thread).'#bug-reports';
    }
}
