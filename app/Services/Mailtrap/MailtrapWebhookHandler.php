<?php

namespace App\Services\Mailtrap;

use App\Models\User;
use App\Services\NewsletterMailtrapEventHandler;
use Illuminate\Support\Facades\Log;

class MailtrapWebhookHandler
{
    public function __construct(
        private readonly NewsletterMailtrapEventHandler $newsletterMailtrapEventHandler,
    ) {}

    public function handle(array $eventData): string
    {
        $event = $eventData['event'] ?? null;

        $newsletterResult = $this->newsletterMailtrapEventHandler->handle($eventData);

        if ($newsletterResult !== null) {
            return $newsletterResult;
        }

        return match ($event) {
            'bounce', 'reject' => $this->handleBounced($eventData), // permanent-style failures; repeated bounces cost real money.
            'soft bounce' => $this->handleSoftBounce($eventData), // temporary failure, don't suppress the user for this alone.
            'spam' => $this->handleSpam($eventData), // we want to stop sending to whomever complains.
            'unsubscribe' => $this->handleUnsubscribed($eventData), // we want to stop sending to whomever unsubscribes.
            'open', 'click' => $this->handleTracking($eventData), // we're not tracking opens/clicks here.
            'delivery' => $this->handleDelivered($eventData),
            'suspension' => $this->handleSuspension($eventData), // Mailtrap paused delivery to this recipient/domain; log for now.
            default => $this->handleUnknown($eventData),
        };
    }

    private function handleBounced(array $eventData): string
    {
        Log::channel('mailtrap')->info('Mailtrap bounce/reject event.', [
            'event_data' => $eventData,
        ]);

        $recipient = $eventData['email'] ?? null;

        if (! $recipient) {
            return 'Missing recipient.';
        }

        $user = User::where('email', $recipient)->first();

        if (! $user) {
            return 'User not found.';
        }

        /*
         * Mark as bounced. Enforced via NotificationPreferenceService::isSuppressed(), checked in the
         * via() guard of ThreadUpdatedNotification, ThreadDigestNotification, and
         * PluginUpdatedNotification before sending optional notification mail.
         */
        $user->forceFill([
            'email_bounced_at' => now(),
            'email_status' => 'bounced',
            'receives_email' => false,
        ])->save();

        return 'User marked as bounced.';
    }

    private function handleSoftBounce(array $eventData): string
    {
        Log::channel('mailtrap')->info('Mailtrap soft bounce event.', [
            'event_data' => $eventData,
        ]);

        return 'Soft bounce logged.';
    }

    private function handleSpam(array $eventData): string
    {
        Log::channel('mailtrap')->warning('Mailtrap spam complaint event.', [
            'event_data' => $eventData,
        ]);

        $recipient = $eventData['email'] ?? null;

        if (! $recipient) {
            return 'Missing recipient.';
        }

        $user = User::where('email', $recipient)->first();

        if (! $user) {
            return 'User not found.';
        }

        // receives_email = false below is what NotificationPreferenceService::isSuppressed() checks
        // to stop future optional notification mail; category preference rows are left as-is.

        $user->forceFill([
            'email_complained_at' => now(),
            'email_status' => 'complained',
            'receives_email' => false,
        ])->save();

        return 'User marked as complained.';
    }

    private function handleUnsubscribed(array $eventData): string
    {
        Log::channel('mailtrap')->info('Mailtrap unsubscribe event.', [
            'event_data' => $eventData,
        ]);

        $recipient = $eventData['email'] ?? null;

        if (! $recipient) {
            return 'Missing recipient.';
        }

        $user = User::where('email', $recipient)->first();

        if (! $user) {
            return 'User not found.';
        }

        // receives_email = false below is what NotificationPreferenceService::isSuppressed() checks
        // to stop future optional notification mail; category preference rows are left as-is.

        $user->forceFill([
            'unsubscribed_at' => now(),
            'receives_email' => false,
            'notify_threads' => false,
            'notify_messages' => false,
            'notify_marketing' => false,
        ])->save();

        return 'User unsubscribed from email.';
    }

    private function handleTracking(array $eventData): string
    {
        Log::channel('mailtrap')->info('Mailtrap tracking event.', [
            'event_data' => $eventData,
        ]);

        return 'Tracking event logged.';
    }

    private function handleDelivered(array $eventData): string
    {
        $recipient = $eventData['email'] ?? null;
        $timestamp = $eventData['timestamp'] ?? null;

        if (! $recipient) {
            return 'Missing recipient.';
        }

        $user = User::where('email', $recipient)->first();

        if (! $user) {
            return 'User not found.';
        }

        $user->forceFill([
            'email_last_delivered_at' => $timestamp
                ? now()->setTimestamp((int) $timestamp)
                : now(),
            'email_status' => 'deliverable',
        ])->save();

        return 'Last delivered timestamp updated.';
    }

    private function handleSuspension(array $eventData): string
    {
        Log::channel('mailtrap')->warning('Mailtrap suspension event.', [
            'event_data' => $eventData,
        ]);

        return 'Suspension event logged.';
    }

    private function handleUnknown(array $eventData): string
    {
        Log::channel('mailtrap')->notice('Unknown Mailtrap event.', [
            'event_data' => $eventData,
        ]);

        return 'Unknown event logged.';
    }
}
