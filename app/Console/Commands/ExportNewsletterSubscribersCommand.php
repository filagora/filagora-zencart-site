<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Services\NewsletterSubscriberService;
use Illuminate\Console\Command;
use SplFileObject;

class ExportNewsletterSubscribersCommand extends Command
{
    protected $signature = 'newsletter:export-subscribers
        {path : Destination CSV path for the sendable subscriber export}';

    protected $description = 'Export sendable newsletter subscribers to CSV for an external sending provider.';

    public function __construct(
        private readonly NewsletterSubscriberService $newsletterSubscriberService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Unable to create export directory [{$directory}].");

            return self::FAILURE;
        }

        if ((file_exists($path) && ! is_writable($path)) || ! is_writable($directory)) {
            $this->error("Export path [{$path}] is not writable.");

            return self::FAILURE;
        }

        $file = new SplFileObject($path, 'w');
        $file->fputcsv([
            'email',
            'name',
            'status',
            'source',
            'subscribed_at',
            'confirmed_at',
            'unsubscribe_url',
        ]);

        $count = 0;

        NewsletterSubscriber::query()
            ->sendable()
            ->whereNull('suppression_hash')
            ->orderBy('id')
            ->cursor()
            ->each(function (NewsletterSubscriber $subscriber) use ($file, &$count): void {
                $file->fputcsv([
                    $subscriber->email,
                    $subscriber->name,
                    $subscriber->status?->value ?? (string) $subscriber->status,
                    $subscriber->source,
                    optional($subscriber->subscribed_at)?->toIso8601String(),
                    optional($subscriber->confirmed_at)?->toIso8601String(),
                    $this->newsletterSubscriberService->signedUnsubscribeUrl($subscriber),
                ]);

                $count++;
            });

        $this->info("Exported {$count} sendable newsletter subscriber(s) to {$path}.");

        return self::SUCCESS;
    }
}
