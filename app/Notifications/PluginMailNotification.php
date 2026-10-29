<?php

namespace App\Notifications;

use App\Models\User;
use Filagora\Notifications\Services\Unsubscribe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One plugin mail, with lara-forum's wording and layout. The plugins package's own mail is
 * switched off (`filagora-plugins.mail.enabled`) and `SendPluginMail` builds these from the same
 * events. The subject and the view's data are worked out when the event happens, so a queued mail
 * does not depend on the rows still being there.
 *
 * `$view` is the name under `emails.notifications`; `$withUnsubscribe` adds the manage-subscriptions
 * button, the preferences and unsubscribe footer and the List-Unsubscribe headers (plugin updates).
 */
class PluginMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const UPDATES_CATEGORY = 'plugins.release_updates';

    /**
     * @param  string  $kind  What happened, in the plugins package's words (`submitted`, `release_approved`, ...)
     * @param  array<string, scalar|null>  $subjectReplace
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $kind,
        private readonly string $subjectKey,
        private readonly array $subjectReplace,
        private readonly string $view,
        private readonly array $data,
        private readonly bool $withUnsubscribe = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->fromSupportAddress(new MailMessage)
            ->subject(__('notifications.plugin.subjects.'.$this->subjectKey, $this->subjectReplace));

        if (! $this->withUnsubscribe) {
            return $message->markdown('emails.notifications.'.$this->view, $this->data);
        }

        $managePreferencesUrl = route('profile.show', ['tab' => 'settings']);
        $unsubscribe = app(Unsubscribe::class);
        $isMember = $notifiable instanceof User;

        $message->markdown('emails.notifications.'.$this->view, $this->data + [
            'manageSubscriptionsUrl' => route('profile.show', ['tab' => 'subscriptions']),
            'managePreferencesUrl' => $managePreferencesUrl,
            'unsubscribeUrl' => $isMember ? $unsubscribe->url($notifiable, self::UPDATES_CATEGORY) : $managePreferencesUrl,
        ]);

        return $isMember ? $unsubscribe->decorate($message, $notifiable, self::UPDATES_CATEGORY) : $message;
    }

    /**
     * Plugin mail comes from the plugin support address when one is set, as before.
     */
    private function fromSupportAddress(MailMessage $message): MailMessage
    {
        $configured = trim((string) (config('filagora-plugins.mail.support_email')));
        $address = filter_var($configured, FILTER_VALIDATE_EMAIL) !== false ? $configured : trim((string) config('mail.from.address', ''));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return $message;
        }

        $name = config('mail.from.name');

        return $message->from($address, is_string($name) && $name !== '' ? $name : null);
    }
}
