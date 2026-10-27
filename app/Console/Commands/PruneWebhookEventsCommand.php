<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class PruneWebhookEventsCommand extends Command
{
    protected $signature = 'webhooks:prune-events {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete webhook idempotency records whose retention window has passed.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = WebhookEvent::query()->where('expires_at', '<', now());
        $count = (int) $query->count();

        if ($count > 0 && ! $dryRun) {
            $query->delete();
        }

        $this->info($dryRun
            ? "Would prune {$count} expired webhook event record(s)."
            : "Pruned {$count} expired webhook event record(s).");

        return self::SUCCESS;
    }
}
