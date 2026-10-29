<?php

namespace App\Observers;

use App\Notifications\EntitlementRequestMailNotification;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Entitlements\Models\EntitlementRequest;
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
    }
}
