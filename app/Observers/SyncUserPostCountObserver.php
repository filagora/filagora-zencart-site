<?php

namespace App\Observers;

use App\Services\UserPostCountService;
use Filagora\Forums\Models\Post;

/**
 * Maintains users.forum_posts_count for the thread-page author badge.
 *
 * Why a column and not the TTL cache it replaces: the badge's
 * `COUNT(*) ... GROUP BY author_id` is covered by posts_author_created_at_idx
 * but still reads one index entry per post, so a page carrying a prolific
 * author cost tens of thousands of entry reads to render one number. No index
 * fixes that -- every candidate is covering and reads the same entries -- so
 * the count has to stop being recomputed. A TTL cache (aea8b39) got the load
 * down ~99.8% but left two warts a column removes outright: the badge lagged a
 * new post by up to the TTL, so an author refreshing after posting saw an
 * unchanged count and could read it as "my post did not save"; and every
 * deploy runs optimize:clear, whose RedisStore::flush() FLUSHDBs the whole
 * cache database, forcing a full cold re-warm (measured 29 Aug 2026: ~250k
 * index-entry reads per deploy, roughly tripling the cache's residual cost
 * across a busy deploy day).
 *
 * Deliberately NOT gated by ForumStatsSyncScope, unlike SyncForumStatsObserver.
 * That scope exists so the hot reply paths can skip the forum-wide recompute
 * and apply their own delta instead (ForumController::storeReply,
 * ForumThreadCreationService::createThread both wrap creation in
 * withoutPostSync). Those are exactly the paths that must still move this
 * counter, and a +1 is not the expensive work the scope was created to avoid.
 *
 * Counts every row in posts for the author, with no is_deleted or
 * moderation_status filter, because that is precisely what the aggregate it
 * replaces counted. Do not "fix" it to a visibility-aware count without
 * changing the badge's meaning -- MembersController::visiblePostCount is the
 * filtered one.
 *
 * Bulk paths that bypass model events do not maintain this counter:
 * ImportVbulletinPostsCommand writes through DB::table('posts')->upsert().
 * Run users:sync-post-counts after any such import, the same way forum
 * counters need forums:sync-stats.
 */
class SyncUserPostCountObserver
{
    public function __construct(private readonly UserPostCountService $userPostCountService) {}

    public function created(Post $post): void
    {
        $this->userPostCountService->applyDelta((int) $post->author_id, 1);
    }

    /**
     * Only an author reassignment moves this counter. A body edit, a
     * moderation change, or a thread move must not touch it, since the count
     * is unfiltered and keyed solely on author_id.
     */
    public function updated(Post $post): void
    {
        if (! $post->wasChanged('author_id')) {
            return;
        }

        $originalAuthorId = (int) ($post->getOriginal('author_id') ?? 0);
        $currentAuthorId = (int) $post->author_id;

        if ($originalAuthorId === $currentAuthorId) {
            return;
        }

        $this->userPostCountService->applyDelta($originalAuthorId, -1);
        $this->userPostCountService->applyDelta($currentAuthorId, 1);
    }

    public function deleted(Post $post): void
    {
        $this->userPostCountService->applyDelta((int) $post->author_id, -1);
    }

    /**
     * Post doesn't use SoftDeletes today, so this never actually fires --
     * handled as a create for safety in case that ever changes, matching
     * SyncForumStatsObserver.
     */
    public function restored(Post $post): void
    {
        $this->userPostCountService->applyDelta((int) $post->author_id, 1);
    }
}
