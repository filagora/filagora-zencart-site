<?php

namespace App\Listeners;

use App\Notifications\PrivacyMailNotification;
use Filagora\Compliance\Events\PrivacyReplySent;
use Filagora\Compliance\Events\PrivacyRequestStatusChanged;
use Filagora\Compliance\Events\PrivacyRequestSubmitted;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * The site's privacy request mail, from the compliance package's events, in place of the package's
 * own (switched off in config/filagora-compliance.php).
 */
class SendPrivacyMail
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function submitted(PrivacyRequestSubmitted $event): void
    {
        $this->notifyRequester($event->request->user, new PrivacyMailNotification(PrivacyMailNotification::SUBMITTED_USER, $event->request));

        $staff = trim($this->settings->string('dsar_request_email'));

        if (filter_var($staff, FILTER_VALIDATE_EMAIL)) {
            Notification::route('mail', $staff)->notify(new PrivacyMailNotification(PrivacyMailNotification::SUBMITTED_STAFF, $event->request));
        }
    }

    public function statusChanged(PrivacyRequestStatusChanged $event): void
    {
        $this->notifyRequester($event->request->user, new PrivacyMailNotification(PrivacyMailNotification::STATUS_UPDATED, $event->request, $event->from, $event->to));
    }

    public function replied(PrivacyReplySent $event): void
    {
        $this->notifyRequester($event->request->user, new PrivacyMailNotification(PrivacyMailNotification::STAFF_REPLY, $event->request, message: $event->message));
    }

    private function notifyRequester(mixed $user, PrivacyMailNotification $notification): void
    {
        if ($user instanceof Model && method_exists($user, 'notify')) {
            $user->notify($notification);
        }
    }
}
