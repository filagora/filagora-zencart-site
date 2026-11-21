<?php

namespace App\Observers;

use App\Notifications\BellNotification;
use App\Notifications\EntitlementRequestMailNotification;
use App\Notifications\SiteCategories;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the member their entitlement request was received and when its status changes, and tells
 * staff (at the `entitlement_request_email` setting) when one is made, as the old site did.
 */
class EntitlementRequestObserver
{
    public function created(EntitlementRequest $request): void
    {
        $request->loadMissing('user');
        $request->user?->notify(new EntitlementRequestMailNotification(EntitlementRequestMailNotification::SUBMITTED_USER, $request));

        $staff = trim(app(SettingsRegistry::class)->string('entitlement_request_email'));

        if (filter_var($staff, FILTER_VALIDATE_EMAIL)) {
            Notification::route('mail', $staff)->notify(new EntitlementRequestMailNotification(EntitlementRequestMailNotification::SUBMITTED_STAFF, $request));
        }
    }

    public function updated(EntitlementRequest $request): void
    {
        if (! $request->wasChanged('status')) {
            return;
        }

        $request->loadMissing('user');
        $from = (string) $request->getRawOriginal('status');
        $to = (string) ($request->status->value ?? $request->status);

        if ($request->user === null || $from === $to) {
            return;
        }

        $request->user->notify(new EntitlementRequestMailNotification(EntitlementRequestMailNotification::STATUS_UPDATED, $request, $from, $to));

        if (app(Preferences::class)->allows($request->user, SiteCategories::ENTITLEMENT_REQUESTS, NotificationChannel::IN_APP)) {
            $request->user->notify(new BellNotification(__('notifications.entitlement_request_updated.title'), ucfirst(str_replace('_', ' ', $to)), route('profile.show', ['tab' => 'entitlements']), SiteCategories::ENTITLEMENT_REQUESTS));
        }
    }
}
