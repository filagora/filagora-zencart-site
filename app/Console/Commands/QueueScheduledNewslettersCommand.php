<?php

namespace App\Console\Commands;

use App\Models\Newsletter;
use App\NewsletterStatus;
use App\Services\NewsletterCampaignSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('newsletter:queue-scheduled')]
#[Description('Queue due scheduled newsletter campaigns for sending.')]
class QueueScheduledNewslettersCommand extends Command
{
    public function __construct(
        private readonly NewsletterCampaignSender $newsletterCampaignSender,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $processed = 0;
        $queued = 0;
        $dispatched = 0;

        Newsletter::query()
            ->where('status', NewsletterStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (Newsletter $newsletter) use (&$processed, &$queued, &$dispatched): void {
                $processed++;

                try {
                    $result = $this->newsletterCampaignSender->queue($newsletter);
                    $queued += $result['queued'];
                    $dispatched += $result['dispatched'];
                    $this->info("Queued newsletter [{$newsletter->id}] with {$result['queued']} delivery record(s); dispatched {$result['dispatched']} recipient job(s).");
                } catch (RuntimeException $exception) {
                    $this->warn("Skipped newsletter [{$newsletter->id}]: {$exception->getMessage()}");
                }
            });

        $released = $this->newsletterCampaignSender->dispatchQueuedSends();
        $dispatched += $released;

        $this->info("Processed {$processed} due scheduled newsletter(s); queued {$queued} delivery record(s); dispatched {$dispatched} recipient job(s).");

        return self::SUCCESS;
    }
}
