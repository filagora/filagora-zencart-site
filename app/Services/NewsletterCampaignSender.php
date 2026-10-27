<?php

namespace App\Services;

use App\Jobs\SendNewsletterCampaignEmail;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use App\NewsletterSendStatus;
use App\NewsletterStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NewsletterCampaignSender
{
    /**
     * @return array{queued: int, dispatched: int, skipped: int}
     */
    public function queue(Newsletter $newsletter): array
    {
        $sendIds = DB::transaction(function () use ($newsletter): array {
            $lockedNewsletter = Newsletter::query()
                ->whereKey($newsletter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedNewsletter->status, [NewsletterStatus::Draft, NewsletterStatus::Scheduled], true)) {
                throw new RuntimeException('Only draft or scheduled newsletters can be queued for sending.');
            }

            if ($lockedNewsletter->sends()->exists()) {
                throw new RuntimeException('This newsletter already has delivery records.');
            }

            $sendIds = [];

            NewsletterSubscriber::query()
                ->sendable()
                ->whereNull('suppression_hash')
                ->orderBy('id')
                ->chunkById(500, function ($subscribers) use ($lockedNewsletter, &$sendIds): void {
                    foreach ($subscribers as $subscriber) {
                        $send = NewsletterSend::query()->create([
                            'newsletter_id' => $lockedNewsletter->id,
                            'newsletter_subscriber_id' => $subscriber->id,
                            'email' => $subscriber->email,
                            'normalized_email' => $subscriber->normalized_email,
                            'name' => $subscriber->name,
                            'status' => NewsletterSendStatus::Queued,
                            'queued_at' => now(),
                        ]);

                        $sendIds[] = $send->id;
                    }
                });

            if ($sendIds === []) {
                return [];
            }

            $lockedNewsletter->fill([
                'status' => NewsletterStatus::Sending,
            ]);
            $lockedNewsletter->save();

            return $sendIds;
        });

        return [
            'queued' => count($sendIds),
            'dispatched' => $this->dispatchQueuedSends($newsletter),
            'skipped' => 0,
        ];
    }

    public function dispatchQueuedSends(?Newsletter $newsletter = null): int
    {
        $capacity = $this->remainingDailyCapacity();

        if ($capacity <= 0) {
            return 0;
        }

        $sendIds = NewsletterSend::query()
            ->where('status', NewsletterSendStatus::Queued->value)
            ->whereNull('dispatched_at')
            ->whereHas('newsletter', fn ($query) => $query->where('status', NewsletterStatus::Sending->value))
            ->when($newsletter instanceof Newsletter, fn ($query) => $query->where('newsletter_id', $newsletter->id))
            ->orderBy('id')
            ->limit($capacity)
            ->pluck('id');

        foreach ($sendIds as $sendId) {
            NewsletterSend::query()
                ->whereKey($sendId)
                ->update(['dispatched_at' => now()]);

            SendNewsletterCampaignEmail::dispatch($sendId);
        }

        return $sendIds->count();
    }

    public function remainingDailyCapacity(): int
    {
        $limit = (int) config('newsletter.delivery.campaign_daily_limit', 90);

        if ($limit <= 0) {
            return 0;
        }

        $used = NewsletterSend::query()
            ->whereNotNull('dispatched_at')
            ->whereDate('dispatched_at', today())
            ->count();

        return max(0, $limit - $used);
    }
}
