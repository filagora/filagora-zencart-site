<?php

namespace App\Services;

use App\Models\NewsletterEvent;
use App\Models\NewsletterSend;
use App\NewsletterEventType;
use App\NewsletterSendStatus;
use App\NewsletterSubscriberStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class NewsletterMailgunEventHandler
{
    public function handle(array $eventData): ?string
    {
        $send = $this->resolveSend($eventData);

        if (! $send instanceof NewsletterSend) {
            return null;
        }

        $eventType = $this->eventType($eventData);

        if ($eventType instanceof NewsletterEventType) {
            NewsletterEvent::query()->create([
                'newsletter_send_id' => $send->id,
                'type' => $eventType,
                'occurred_at' => $this->occurredAt($eventData),
                'payload' => $eventData,
            ]);
        }

        $providerMessageId = $this->providerMessageId($eventData);
        if ($providerMessageId !== null && $send->provider_message_id === null) {
            $send->provider_message_id = $providerMessageId;
        }

        match ($eventType) {
            NewsletterEventType::Delivered => $this->markDelivered($send),
            NewsletterEventType::Failed, NewsletterEventType::Bounced => $this->markFailed($send, $eventData),
            NewsletterEventType::Complained => $this->markComplained($send),
            NewsletterEventType::Unsubscribed => $this->markUnsubscribed($send),
            default => $send->save(),
        };

        return 'Newsletter event recorded.';
    }

    private function resolveSend(array $eventData): ?NewsletterSend
    {
        $newsletterSendId = $this->newsletterSendId($eventData);

        if ($newsletterSendId !== null) {
            return NewsletterSend::query()->find($newsletterSendId);
        }

        $providerMessageId = $this->providerMessageId($eventData);

        if ($providerMessageId !== null) {
            $send = NewsletterSend::query()
                ->where('provider_message_id', $providerMessageId)
                ->latest('id')
                ->first();

            if ($send instanceof NewsletterSend) {
                return $send;
            }
        }

        $recipient = $this->recipient($eventData);

        if ($recipient === null) {
            return null;
        }

        return NewsletterSend::query()
            ->where('normalized_email', Str::lower($recipient))
            ->whereIn('status', [
                NewsletterSendStatus::Queued->value,
                NewsletterSendStatus::Sending->value,
                NewsletterSendStatus::Sent->value,
            ])
            ->latest('id')
            ->first();
    }

    private function newsletterSendId(array $eventData): ?int
    {
        $variables = $eventData['user-variables'] ?? $eventData['user_variables'] ?? [];

        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            $variables = is_array($decoded) ? $decoded : [];
        }

        $id = is_array($variables) ? ($variables['newsletter_send_id'] ?? null) : null;

        return is_numeric($id) ? (int) $id : null;
    }

    private function providerMessageId(array $eventData): ?string
    {
        $value = $eventData['message']['headers']['message-id']
            ?? $eventData['message']['headers']['message_id']
            ?? $eventData['Message-Id']
            ?? $eventData['message-id']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value, '<> ') : null;
    }

    private function recipient(array $eventData): ?string
    {
        $recipient = $eventData['recipient'] ?? null;

        return is_string($recipient) && trim($recipient) !== '' ? trim($recipient) : null;
    }

    private function eventType(array $eventData): ?NewsletterEventType
    {
        $event = Str::lower((string) ($eventData['event'] ?? ''));
        $reason = Str::lower((string) ($eventData['reason'] ?? ''));

        return match ($event) {
            'delivered' => NewsletterEventType::Delivered,
            'failed' => str_contains($reason, 'bounce') ? NewsletterEventType::Bounced : NewsletterEventType::Failed,
            'complained' => NewsletterEventType::Complained,
            'unsubscribed' => NewsletterEventType::Unsubscribed,
            'opened' => NewsletterEventType::Opened,
            'clicked' => NewsletterEventType::Clicked,
            default => null,
        };
    }

    private function occurredAt(array $eventData): CarbonInterface
    {
        $timestamp = $eventData['timestamp'] ?? null;

        return is_numeric($timestamp) ? now()->setTimestamp((int) $timestamp) : now();
    }

    private function markDelivered(NewsletterSend $send): void
    {
        $send->fill([
            'status' => NewsletterSendStatus::Sent,
            'sent_at' => $send->sent_at ?? now(),
            'failed_at' => null,
            'error' => null,
        ]);
        $send->save();
    }

    private function markFailed(NewsletterSend $send, array $eventData): void
    {
        $send->fill([
            'status' => NewsletterSendStatus::Failed,
            'failed_at' => now(),
            'error' => (string) ($eventData['delivery-status']['message'] ?? $eventData['reason'] ?? 'Mailgun delivery failure.'),
        ]);
        $send->save();

        if (($eventData['severity'] ?? null) === 'permanent') {
            $subscriber = $send->subscriber;

            if ($subscriber !== null) {
                $subscriber->fill([
                    'status' => NewsletterSubscriberStatus::Bounced,
                    'bounced_at' => $subscriber->bounced_at ?? now(),
                    'unsubscribe_reason' => 'mailgun_bounce',
                ]);
                $subscriber->save();
            }
        }
    }

    private function markComplained(NewsletterSend $send): void
    {
        $send->fill([
            'status' => NewsletterSendStatus::Failed,
            'failed_at' => now(),
            'error' => 'Mailgun complaint event.',
        ]);
        $send->save();

        $subscriber = $send->subscriber;

        if ($subscriber !== null) {
            $subscriber->fill([
                'status' => NewsletterSubscriberStatus::Complained,
                'complained_at' => $subscriber->complained_at ?? now(),
                'unsubscribe_reason' => 'mailgun_complaint',
            ]);
            $subscriber->save();
        }
    }

    private function markUnsubscribed(NewsletterSend $send): void
    {
        $send->save();

        $subscriber = $send->subscriber;

        if ($subscriber !== null) {
            app(NewsletterSubscriberService::class)->unsubscribe($subscriber, 'mailgun_unsubscribe');
        }
    }
}
