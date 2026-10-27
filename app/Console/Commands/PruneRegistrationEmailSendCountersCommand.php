<?php

namespace App\Console\Commands;

use App\Models\RegistrationEmailSendCounter;
use Illuminate\Console\Command;

class PruneRegistrationEmailSendCountersCommand extends Command
{
    protected $signature = 'registration:prune-send-counters
        {--days=90 : Age in days before a daily verification-email counter row is pruned}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete daily verification-email send counters older than the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days)->toDateString();

        $query = RegistrationEmailSendCounter::query()->where('send_date', '<', $cutoff);
        $count = (int) $query->count();

        if ($count > 0 && ! $dryRun) {
            $query->delete();
        }

        $this->info($dryRun
            ? "Would prune {$count} daily verification-email send counter(s)."
            : "Pruned {$count} daily verification-email send counter(s).");

        return self::SUCCESS;
    }
}
