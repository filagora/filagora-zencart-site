<?php

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use App\Services\NewsletterSubscriberService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsletterConfirmSubscriptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NewsletterSubscriber $subscriber,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $newsletterSubscriberService = app(NewsletterSubscriberService::class);
        $confirmUrl = $newsletterSubscriberService->signedConfirmationUrl($this->subscriber);
        $unsubscribeUrl = $newsletterSubscriberService->signedUnsubscribeUrl($this->subscriber);

        return (new MailMessage)
            ->subject('Confirm your Zen Cart newsletter subscription')
            ->line('Please confirm that you want to receive the Zen Cart newsletter by email.')
            ->action('Confirm Newsletter Subscription', $confirmUrl)
            ->line('If you did not request this, you can ignore this email or unsubscribe below.')
            ->line('Unsubscribe: '.$unsubscribeUrl);
    }
}
