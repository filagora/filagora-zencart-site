<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Maintains users.forum_posts_count, the counter behind the thread-page
 * author post-count badge.
 *
 * Mirrors ForumStatsService's post_count handling: an atomic delta on the
 * hot write path, and a full recompute available as the drift safety net.
 * See SyncUserPostCountObserver for why this is a column rather than a cache.
 */
class UserPostCountService
{
    /**
     * Atomically adjust one author's badge counter without recomputing it.
     *
     * Uses the base query builder rather than Eloquent's increment(): the
     * Eloquent version folds updated_at into the same statement, and bumping
     * every posting user's updated_at on every reply is a side effect this
     * counter has no business causing. ForumStatsService accepts that touch
     * on forums because forums are few and rarely written; users are neither.
     *
     * A plain decrement rather than a floor-at-zero GREATEST() (which SQLite
     * lacks) -- any drift from a bookkeeping slip is corrected by the full
     * recompute in users:sync-post-counts, the same safety net every
     * counter-cache approach relies on.
     */
    public function applyDelta(int $userId, int $delta): void
    {
        if ($userId <= 0 || $delta === 0) {
            return;
        }

        $query = DB::table('users')->where('id', $userId);

        if ($delta > 0) {
            $query->increment('forum_posts_count', $delta);

            return;
        }

        $query->decrement('forum_posts_count', abs($delta));
    }

    /**
     * Recompute the counter from posts for the given authors.
     *
     * @param  list<int>  $userIds
     */
    public function syncForUserIds(array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));

        if ($userIds === []) {
            return;
        }

        $counts = DB::table('posts')
            ->selectRaw('author_id, COUNT(*) as aggregate')
            ->whereIn('author_id', $userIds)
            ->groupBy('author_id')
            ->pluck('aggregate', 'author_id');

        foreach ($userIds as $userId) {
            $count = (int) ($counts[$userId] ?? 0);

            // GROUP BY returns no row for an author with no posts; their
            // counter still has to be considered, or a user who lost their
            // last post keeps a stale non-zero badge forever. The !=  guard
            // makes that free in the common case: matching no row costs no
            // write, which matters most on the initial backfill, where every
            // user already reads 0 from the column default.
            DB::table('users')
                ->where('id', $userId)
                ->where('forum_posts_count', '!=', $count)
                ->update(['forum_posts_count' => $count]);
        }
    }

    /**
     * Recompute every user's counter, in id order, in chunks.
     *
     * @param  callable(int): void|null  $onChunk  Receives the chunk size processed.
     */
    public function syncAll(int $chunkSize = 500, ?callable $onChunk = null): int
    {
        $processed = 0;

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunk(max(1, $chunkSize), function ($users) use (&$processed, $onChunk): void {
                $userIds = array_map(static fn ($user): int => (int) $user->id, $users->all());

                $this->syncForUserIds($userIds);

                $processed += count($userIds);

                if ($onChunk !== null) {
                    $onChunk(count($userIds));
                }
            });

        return $processed;
    }
}
