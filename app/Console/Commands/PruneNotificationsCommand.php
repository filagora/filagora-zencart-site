<?php

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune
        {--read-days=60 : Delete read notifications older than this many days}
        {--all-days=180 : Delete any notification older than this many days, read or not}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Trim the bell notification history: read notifications past the read window, and anything past the hard age limit.';

    /**
     * Rows removed per DELETE. Bounds how long the table is locked in a single
     * statement on a large history.
     */
    private const CHUNK = 1000;

    public function handle(): int
    {
        $readDays = max(1, (int) $this->option('read-days'));
        $allDays = max($readDays, (int) $this->option('all-days'));
        $dryRun = (bool) $this->option('dry-run');

        $readCutoff = now()->subDays($readDays);
        $allCutoff = now()->subDays($allDays);

        $readScope = static fn (Builder $query): Builder => $query
            ->whereNotNull('read_at')
            ->where('created_at', '<', $readCutoff);
        $oldScope = static fn (Builder $query): Builder => $query
            ->where('created_at', '<', $allCutoff);

        $readCount = (int) $this->base($readScope)->count();
        $oldCount = (int) $this->base($oldScope)->whereNull('read_at')->count();

        if (! $dryRun) {
            $this->deleteInChunks($readScope);
            $this->deleteInChunks($oldScope);
        }

        $this->info($dryRun
            ? "Would prune {$readCount} read + {$oldCount} old-unread notification(s)."
            : "Pruned {$readCount} read + {$oldCount} old-unread notification(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Closure(Builder): Builder  $scope
     */
    private function base(Closure $scope): Builder
    {
        return $scope(DB::table('notifications'));
    }

    /**
     * Select-then-delete by id so it stays portable (no DELETE ... LIMIT) and
     * never holds a lock over more than CHUNK rows at once.
     *
     * @param  Closure(Builder): Builder  $scope
     */
    private function deleteInChunks(Closure $scope): void
    {
        do {
            $ids = $this->base($scope)->limit(self::CHUNK)->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('notifications')->whereIn('id', $ids->all())->delete();
            }
        } while ($ids->count() === self::CHUNK);
    }
}
