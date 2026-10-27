<?php

namespace App\Services\Mailgun;

use App\Models\User;
use App\Services\NewsletterMailgunEventHandler;
use App\Services\NotificationPreferenceService;
use Illuminate\Support\Facades\Log;

class MailgunWebhookHandler
{
    public function __construct(
        private readonly NewsletterMailgunEventHandler $newsletterMailgunEventHandler,
    ) {}

    public function handle(array $eventData): string
    {
        $event = $eventData['event'] ?? null;

        if (($eventData['flags']['is-test-mode'] ?? false) === true) {
            Log::info('Mailgun test webhook received.', [
                'event_data' => $eventData,
            ]);
        }

        $newsletterResult = $this->newsletterMailgunEventHandler->handle($eventData);

        if ($newsletterResult !== null) {
            return $newsletterResult;
        }

        return match ($event) {
            'failed' => $this->handleFailed($eventData), // For failures we track that delivery didn't succeed. Repeated bounces, or permanent failures trigger a block.
            'complained' => $this->handleComplained($eventData), // we want to stop sending to whomever complains.
            'unsubscribed' => $this->handleUnsubscribed($eventData), // we want to stop sending to whomever unsubscribes.
            'opened', 'clicked' => $this->handleTracking($eventData), // we're not tracking opens/clicks.
            'delivered' => $this->handleDelivered($eventData),
            'accepted' => 'Accepted event acknowledged.', // this just means we got the message onto Mailgun's queue.
            'stored' => $this->handleStored($eventData), // stored is for incoming messages, which we don't use.
            default => $this->handleUnknown($eventData),
        };
    }

    private function handleFailed(array $eventData): string
    {
        Log::channel('mailgun')->info('Mailgun failed event.', [
            'event_data' => $eventData,
        ]);

        $recipient = $eventData['recipient'] ?? null;
        $severity = $eventData['severity'] ?? null; // permanent or temporary
        $reason = $eventData['reason'] ?? null; // 'suppress', 'suppress-bounce', 'bounce', 'generic'

        if (! $recipient) {
            return 'Missing recipient.';
        }

        if ($severity !== 'permanent') {
            return 'Temporary failure ignored.';
        }

        $user = User::where('email', $recipient)->first();

        if (! $user) {
            return 'User not found.';
        }

        /*
         * Generic failures may be noisy, so get softer handling
         * Here we assume that if a user has at least 1 post, they are likely an engaged user and we want to give them the benefit of the doubt
         * in case of false positives from certain email providers. We still log the event for monitoring, but we don't mark the user as bounced in this case.
         */
        if ($reason === 'generic' && $user->posts()->exists()) {
            Log::info('Generic permanent failure ignored for active user.', [
                'user_id' => $user->id,
                'email' => $recipient,
            ]);

            return 'Generic failure ignored for active user.';
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

    private function handleComplained(array $eventData): string
    {
        Log::channel('mailgun')->warning('Mailgun complaint event.', [
            'event_data' => $eventData,
        ]);

        $recipient = $eventData['recipient'] ?? null;

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
        Log::channel('mailgun')->info('Mailgun unsubscribe event.', [
            'event_data' => $eventData,
        ]);

        $recipient = $eventData['recipient'] ?? null;

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
        Log::channel('mailgun')->info('Mailgun tracking event.', [
            'event_data' => $eventData,
        ]);

        return 'Tracking event logged.';
    }

    private function handleDelivered(array $eventData): string
    {
        $recipient = $eventData['recipient'] ?? null;
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

    /**
     * We're not using the Stored event service, since that's for incoming mail.
     */
    private function handleStored(array $eventData): string
    {
        Log::channel('mailgun')->info('Mailgun stored message event.', [
            'event_data' => $eventData,
        ]);

        return 'Stored message logged.';
    }

    private function handleUnknown(array $eventData): string
    {
        Log::channel('mailgun')->notice('Unknown Mailgun event.', [
            'event_data' => $eventData,
        ]);

        return 'Unknown event logged.';
    }
}
