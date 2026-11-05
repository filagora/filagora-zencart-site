<?php

namespace App\Console\Commands;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Entitlements\Support\EntitlementCache;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Lists thread subscribers who haven't been seen in a while and, unless
 * --dry-run, revokes their `filagora-zencart.notifications.email_entitlement`
 * grant - the blanket switch checked before any optional notification mail
 * (thread/plugin updates, digests) goes out. This only stops mail to an address nobody's reading; it
 * doesn't touch push/bell preferences or any individual category toggle.
 *
 * "Last active" combines two sources where both exist: `vb_user.lastvisit`
 * (this app mirrors a still-live vBulletin forum - see docs/importers.md -
 * so that's the real activity signal today) and `users.last_seen_at` (this
 * app's own front-end visit tracking, which will matter once it's the forum
 * people actually use). Either missing falls back to `users.created_at`
 * rather than being treated as "ancient", so a freshly created account that
 * hasn't been tracked yet is never mistaken for a long-inactive one.
 *
 * On this app's real data the inactive-subscriber set runs into the tens of
 * thousands (most vBulletin-imported accounts have never visited this app),
 * so the terminal table is capped to a preview and the full set is processed
 * in chunks rather than loaded into memory at once.
 */
class DisableEmailForInactiveSubscribersCommand extends Command
{
    protected $signature = 'notifications:disable-email-inactive-subscribers
        {--days=90 : Days since last activity before a subscriber counts as inactive}
        {--dry-run : List the affected users without changing any grants}
        {--export= : Write the full affected list to this CSV path instead of a terminal preview}
        {--preview=25 : Rows to show in the terminal table; ignored when --export is given}';

    protected $description = 'List thread subscribers inactive for N+ days and revoke their email-notification entitlement.';

    /**
     * Raw SQL for "last known activity", reused across the where/order/select
     * clauses below. Built once per run depending on whether vb_user is
     * present (it exists only once import:vbulletin-sync-remote has run at
     * least once - never in a fresh test database).
     */
    private string $activitySql;

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $exportPath = $this->option('export');
        $previewLimit = max(0, (int) $this->option('preview'));
        $cutoff = now()->subDays($days);
        $entitlement = (string) config('filagora-zencart.notifications.email_entitlement');

        $hasVbUserTable = Schema::hasTable('vb_user');
        $this->activitySql = $hasVbUserTable
            ? 'GREATEST('
                .'COALESCE(FROM_UNIXTIME(NULLIF(vb_user.lastvisit, 0)), users.created_at), '
                .'COALESCE(users.last_seen_at, users.created_at)'
                .')'
            : 'COALESCE(users.last_seen_at, users.created_at)';

        $query = User::query()
            ->whereHas('subscribedThreads')
            // Only users who currently have something to revoke - otherwise
            // a user who never held the grant (or was already revoked by an
            // earlier run) would still show up in the list/count with
            // nothing for the update below to actually change.
            ->whereIn('users.id', UserEntitlementGrant::query()->where('entitlement_key', $entitlement)->active()->select('user_id'))
            ->when($hasVbUserTable, fn (Builder $query) => $query->leftJoin('vb_user', 'vb_user.userid', '=', 'users.id'))
            ->select('users.*')
            ->selectRaw("{$this->activitySql} as effective_last_active")
            ->whereRaw("{$this->activitySql} <= ?", [$cutoff])
            ->withCount('subscribedThreads as thread_subscriptions_count');

        $total = (int) (clone $query)->count();

        if ($total === 0) {
            $this->info("No thread subscribers inactive for {$days}+ days.");

            return self::SUCCESS;
        }

        if ($exportPath !== null) {
            $this->exportToCsv(clone $query, (string) $exportPath);
            $this->info("Wrote {$total} subscriber(s) to {$exportPath}.");
        } elseif ($previewLimit > 0) {
            $this->renderPreview(clone $query, $previewLimit, $total);
        }

        if ($dryRun) {
            $this->info("Would revoke email notifications for {$total} subscriber(s).");

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $affectedUserIds = [];

        $query->chunkById(500, function ($chunk) use ($bar, &$affectedUserIds, $entitlement): void {
            $ids = $chunk->pluck('id')->all();
            $affectedUserIds = [...$affectedUserIds, ...$ids];

            UserEntitlementGrant::query()
                ->whereIn('user_id', $ids)
                ->where('entitlement_key', $entitlement)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'reason' => 'Revoked by notifications:disable-email-inactive-subscribers (inactive subscriber).',
                    'updated_at' => now(),
                ]);

            $bar->advance(count($ids));
        });

        $bar->finish();
        $this->newLine();

        // The bulk update() above bypasses Eloquent, so UserEntitlementGrant's
        // saved() observer never fires to bump each user's cache version -
        // one global bump instead of one Redis round trip per affected user.
        if ($affectedUserIds !== []) {
            app(EntitlementCache::class)->bumpDefaultVersion();
        }

        $this->info('Revoked email notifications for '.count($affectedUserIds).' subscriber(s).');

        return self::SUCCESS;
    }

    private function renderPreview(Builder $query, int $limit, int $total): void
    {
        $preview = $query->orderByRaw($this->activitySql)
            ->limit($limit)
            ->get();

        $this->table(
            ['ID', 'Username', 'Email', 'Last active', 'Subscriptions'],
            $preview->map(fn (User $user): array => [
                $user->id,
                $user->username,
                $user->email,
                $user->effective_last_active ? Carbon::parse($user->effective_last_active)->toDateString() : 'unknown',
                $user->thread_subscriptions_count,
            ])->all(),
        );

        if ($total > $preview->count()) {
            $remaining = $total - $preview->count();
            $this->line("... and {$remaining} more not shown (use --export=path.csv for the full list).");
        }
    }

    private function exportToCsv(Builder $query, string $path): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$path} for writing.");
        }

        fputcsv($handle, ['id', 'username', 'email', 'last_active', 'thread_subscriptions']);

        $query->chunkById(500, function ($chunk) use ($handle): void {
            foreach ($chunk as $user) {
                $lastActive = $user->effective_last_active
                    ? Carbon::parse($user->effective_last_active)->toDateString()
                    : '';

                fputcsv($handle, [
                    $user->id,
                    $user->username,
                    $user->email,
                    $lastActive,
                    $user->thread_subscriptions_count,
                ]);
            }
        });

        fclose($handle);
    }
}
