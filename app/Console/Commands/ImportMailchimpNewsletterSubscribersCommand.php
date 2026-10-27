<?php

namespace App\Console\Commands;

use App\Services\NewsletterSubscriberService;
use Illuminate\Console\Command;
use SplFileObject;

class ImportMailchimpNewsletterSubscribersCommand extends Command
{
    protected $signature = 'newsletter:import-mailchimp
        {path : Path to the Mailchimp CSV export}
        {--dry-run : Parse and report changes without writing subscribers}';

    protected $description = 'Import Mailchimp newsletter subscribers from a CSV export.';

    public function __construct(
        private readonly NewsletterSubscriberService $newsletterSubscriberService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV file [{$path}] does not exist or is not readable.");

            return self::FAILURE;
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = [];
        $stats = [
            'seen' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'suppressed' => 0,
        ];

        foreach ($file as $lineNumber => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($headers === []) {
                $headers = array_map(static fn (mixed $header): string => trim((string) $header), $row);

                continue;
            }

            $stats['seen']++;
            $data = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $data[$header] = $row[$index] ?? null;
            }

            $result = $this->newsletterSubscriberService->importMailchimpRow($data, $dryRun);
            if (! array_key_exists($result, $stats)) {
                $stats[$result] = 0;
            }

            $stats[$result]++;

            if ($result === 'invalid') {
                $this->warn('Skipped invalid email on CSV line '.($lineNumber + 1).'.');
            }
        }

        $mode = $dryRun ? 'dry-run' : 'import';
        $this->info("Mailchimp {$mode} complete.");
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [$metric, (string) $count])->values()->all()
        );

        return self::SUCCESS;
    }
}
