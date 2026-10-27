<?php

namespace App\Jobs;

use App\Services\NewsletterCampaignMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendNewsletterCampaignEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $newsletterSendId,
    ) {
        $this->onQueue('mail');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('newsletter-send:'.$this->newsletterSendId))
                ->expireAfter(300),
        ];
    }

    public function handle(NewsletterCampaignMailer $mailer): void
    {
        $mailer->sendCampaignRecipient($this->newsletterSendId);
    }
}
