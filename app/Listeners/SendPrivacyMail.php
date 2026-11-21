<?php

namespace App\Listeners;

use App\Notifications\BellNotification;
use App\Notifications\PrivacyMailNotification;
use App\Notifications\StaffAlerts;
use App\Notifications\StaffCategories;
use Filagora\Compliance\Events\PrivacyReplySent;
use Filagora\Compliance\Events\PrivacyRequestStatusChanged;
use Filagora\Compliance\Events\PrivacyRequestSubmitted;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * The site's privacy request mail, from the compliance package's events, in place of the package's
 * own (switched off in config/filagora-compliance.php).
 */
class SendPrivacyMail
{
    public function __construct(private readonly SettingsRegistry $settings, private readonly StaffAlerts $staffAlerts) {}

    public function submitted(PrivacyRequestSubmitted $event): void
    {
        $this->notifyRequester($event->request->user, new PrivacyMailNotification(PrivacyMailNotification::SUBMITTED_USER, $event->request));

        // A privacy request has a legal deadline, so if no staff member is set to be told, the team
        // address still gets it.
        $this->staffAlerts->send(
            StaffCategories::PRIVACY_REQUESTS,
            fn () => new PrivacyMailNotification(PrivacyMailNotification::SUBMITTED_STAFF, $event->request),
            [$this->settings->string('dsar_request_email')],
            fn () => new BellNotification(
                __('notifications.staff.privacy_request.title'),
                $event->request->type?->label() ?? strtoupper((string) $event->request->type),
                route('filament.dashboard.resources.privacy-requests.view', ['record' => $event->request->getKey()]),
                StaffCategories::PRIVACY_REQUESTS,
            ),
            guaranteed: true,
        );
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
