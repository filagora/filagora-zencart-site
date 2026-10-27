<?php

namespace App\Services;

use App\Mail\NewsletterCampaignMail;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\NewsletterSendStatus;
use App\NewsletterStatus;
use Throwable;

class NewsletterCampaignMailer
{
    public function __construct(
        private readonly NewsletterDeliveryTransport $transport,
    ) {}

    public function sendPreview(Newsletter $newsletter, string $email): void
    {
        $this->transport->send($email, new NewsletterCampaignMail(
            newsletter: $newsletter,
            unsubscribeUrl: route('newsletter.show'),
            preview: true,
            deliveryProvider: $this->transport->provider(),
        ));
    }

    public function sendCampaignRecipient(int $newsletterSendId): void
    {
        $send = NewsletterSend::query()
            ->with(['newsletter', 'subscriber'])
            ->findOrFail($newsletterSendId);

        if ($send->status === NewsletterSendStatus::Sent) {
            return;
        }

        $subscriber = $send->subscriber;
        if ($subscriber === null || ! $subscriber->isSendable() || $subscriber->suppression_hash !== null) {
            $send->fill([
                'status' => NewsletterSendStatus::Skipped,
                'skipped_at' => now(),
                'error' => 'Subscriber is no longer sendable.',
            ]);
            $send->save();
            $this->completeNewsletterIfReady($send->newsletter_id);

            return;
        }

        $send->fill([
            'status' => NewsletterSendStatus::Sending,
            'error' => null,
        ]);
        $send->save();

        try {
            $this->transport->send($send->email, new NewsletterCampaignMail(
                newsletter: $send->newsletter,
                unsubscribeUrl: app(NewsletterSubscriberService::class)->signedUnsubscribeUrl($subscriber),
                newsletterSendId: $send->id,
                deliveryProvider: $this->transport->provider(),
            ));

            $send->fill([
                'status' => NewsletterSendStatus::Sent,
                'sent_at' => now(),
                'failed_at' => null,
                'error' => null,
            ]);
            $send->save();
            $this->completeNewsletterIfReady($send->newsletter_id);
        } catch (Throwable $throwable) {
            $send->fill([
                'status' => NewsletterSendStatus::Failed,
                'failed_at' => now(),
                'error' => $throwable->getMessage(),
            ]);
            $send->save();
            $this->completeNewsletterIfReady($send->newsletter_id);

            throw $throwable;
        }
    }

    private function completeNewsletterIfReady(int $newsletterId): void
    {
        $newsletter = Newsletter::query()->find($newsletterId);
        if ($newsletter === null) {
            return;
        }

        $hasPendingSends = $newsletter->sends()
            ->whereIn('status', [
                NewsletterSendStatus::Queued->value,
                NewsletterSendStatus::Sending->value,
            ])
            ->exists();

        if ($hasPendingSends) {
            return;
        }

        $newsletter->fill([
            'status' => NewsletterStatus::Sent,
            'sent_at' => $newsletter->sent_at ?? now(),
        ]);
        $newsletter->save();
    }
}
