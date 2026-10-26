<?php

namespace App\Services;

use App\Models\User;
use App\Support\RelocationResult;
use Carbon\CarbonInterface;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\PostAttachment;
use Filagora\Forums\Models\PostModerationLog;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Models\ThreadModerationLog;
use Filagora\Forums\Search\ThreadSearchIndex;
use Filagora\Forums\Services\ForumStats;
use Filagora\Forums\Services\Subscriptions;
use Filagora\Forums\Services\ThreadCreation;
use Filagora\Forums\Support\StatsSyncScope;
use Filagora\Search\Indexing\Indexer;
use Filagora\Search\Support\SearchIndexRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place moderator "relocate this content" actions live: move a
 * thread to another forum, move a post to another thread, promote a post
 * into a brand-new thread, and the batch + undo variants. Each action
 * records the inverse operation under a one-shot token so the moderator
 * can undo it from the toast.
 */
class ContentRelocationService
{
    private const UNDO_TTL_MINUTES = 10;

    private const RECENT_LIMIT = 8;

    public function __construct(
        private readonly ForumStats $forumStats,
        private readonly ThreadCreation $threadCreation,
        private readonly Subscriptions $subscriptions,
    ) {}

    public function moveThread(Thread $thread, Forum $destination, ?string $reason, User $moderator): RelocationResult
    {
        $sourceForumId = (int) $thread->forum_id;

        DB::transaction(function () use ($thread, $destination, $reason, $moderator, $sourceForumId): void {
            $thread->forum_id = $destination->id;
            $thread->save();

            ThreadModerationLog::query()->create([
                'thread_id' => $thread->id,
                'moderator_id' => $moderator->id,
                'action' => 'move',
                'reason' => $this->trimReason($reason),
            ]);

            $this->forumStats->syncForForumIds([$sourceForumId, (int) $destination->id]);
        });

        $this->rememberRecentForum($moderator, (int) $destination->id);

        return new RelocationResult(
            destinationUrl: route('threads.show', $thread).'#thread-top',
            undoToken: $this->mintUndoToken('move_thread', $moderator, [[
                'thread_id' => (int) $thread->id,
                'from_forum_id' => $sourceForumId,
            ]]),
            summary: __('forum.relocate.moved_thread', ['forum' => $destination->name]),
        );
    }

    public function movePost(Post $post, Thread $destination, ?string $reason, User $moderator): RelocationResult
    {
        $sourceThreadId = (int) $post->thread_id;
        $op = ['post_id' => (int) $post->id, 'from_thread_id' => $sourceThreadId];

        DB::transaction(function () use ($post, $destination, $reason, $moderator, $sourceThreadId, &$op): void {
            $post->thread_id = $destination->id;
            $post->save();
            $this->repointAttachments((int) $post->id, (int) $destination->id);

            PostModerationLog::query()->create([
                'thread_id' => $sourceThreadId,
                'post_id' => $post->id,
                'moderator_id' => $moderator->id,
                'action' => 'move',
                'reason' => $this->trimReason($reason),
            ]);

            if ($restore = $this->disposeThreadIfEmpty($sourceThreadId, (int) $destination->id)) {
                $op += $restore;
            } else {
                $this->syncThread($sourceThreadId);
            }

            $this->syncThread((int) $destination->id);
        });

        $this->rememberRecentThread($moderator, (int) $destination->id);

        return new RelocationResult(
            destinationUrl: route('threads.show', $destination->id).'#post-'.$post->id,
            undoToken: $this->mintUndoToken('move_post', $moderator, [$op]),
            summary: __('forum.relocate.moved_post', ['thread' => $destination->title]),
            navigateAway: isset($op['restore_thread']),
        );
    }

    /**
     * A thread that can be quietly removed once its last post leaves: no
     * posts, no poll, no bug reports.
     */
    private function threadIsDisposable(Thread $thread): bool
    {
        return ! Post::query()->where('thread_id', $thread->id)->exists()
            && $thread->poll()->doesntExist()
            && $thread->bugReports()->doesntExist();
    }

    /**
     * If the source thread is now empty and disposable, move the relocated
     * posts' audit trail + abuse reports onto the destination (so they
     * survive the delete), snapshot the thread + its subscribers for undo,
     * then delete it. Must run inside the caller's transaction, after every
     * post has left. Returns the keys to merge into the undo op, or null.
     *
     * @return array{restore_thread: array<string, mixed>, restore_subscriber_ids: list<int>}|null
     */
    private function disposeThreadIfEmpty(int $sourceThreadId, int $destinationThreadId): ?array
    {
        $thread = Thread::query()->find($sourceThreadId);
        if (! $thread instanceof Thread || ! $this->threadIsDisposable($thread)) {
            return null;
        }

        // The thread has no posts left, so every log / report on it belongs to
        // a post that now lives in the destination — carry them across.
        PostModerationLog::query()->where('thread_id', $sourceThreadId)->update(['thread_id' => $destinationThreadId]);
        PostReport::query()->where('thread_id', $sourceThreadId)->update(['thread_id' => $destinationThreadId]);

        $subscriberIds = DB::table('thread_subscriptions')
            ->where('thread_id', $sourceThreadId)
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        // Thread-level audit rows (pin/lock/move history) can't move to another
        // thread, so snapshot them for undo — they cascade away with the row.
        $threadLogs = ThreadModerationLog::query()
            ->where('thread_id', $sourceThreadId)
            ->get()
            ->map(static fn (ThreadModerationLog $log): array => $log->getAttributes())
            ->all();

        // last_activity_at is a generated column — it can't be written back.
        $snapshot = collect($thread->getAttributes())->except(['last_activity_at'])->all();
        $forumId = (int) $thread->forum_id;
        $thread->delete();
        $this->forumStats->syncForForumId($forumId);

        return [
            'restore_thread' => $snapshot,
            'restore_subscriber_ids' => $subscriberIds,
            'restore_thread_logs' => $threadLogs,
        ];
    }

    /**
     * Batch variant: dispose each distinct emptied source thread and merge
     * the restore data onto the first op that came from it.
     *
     * @param  list<int>  $sourceThreadIds
     * @param  list<array<string, mixed>>  $ops  mutated in place
     * @return bool whether any thread was removed (the caller's page is stale)
     */
    private function disposeEmptiedSources(array $sourceThreadIds, int $destinationThreadId, array &$ops): bool
    {
        $removedAny = false;

        foreach (array_values(array_unique($sourceThreadIds)) as $threadId) {
            $restore = $this->disposeThreadIfEmpty((int) $threadId, $destinationThreadId);
            if ($restore === null) {
                continue;
            }

            foreach ($ops as $index => $op) {
                if ((int) ($op['from_thread_id'] ?? 0) === (int) $threadId) {
                    $ops[$index] += $restore;
                    break;
                }
            }
            $removedAny = true;
        }

        return $removedAny;
    }

    public function promotePost(Post $post, Forum $destination, string $title, ?string $reason, User $moderator): RelocationResult
    {
        $sourceThreadId = (int) $post->thread_id;
        $op = ['post_id' => (int) $post->id, 'from_thread_id' => $sourceThreadId];

        $newThread = DB::transaction(function () use ($post, $destination, $title, $reason, $moderator, $sourceThreadId, &$op): Thread {
            $newThread = $this->promotePostToThread($post, $destination, $title);
            $this->repointAttachments((int) $post->id, (int) $newThread->id);

            $op['created_thread_id'] = (int) $newThread->id;

            PostModerationLog::query()->create([
                'thread_id' => $sourceThreadId,
                'post_id' => $post->id,
                'moderator_id' => $moderator->id,
                'action' => 'promote',
                'reason' => $this->trimReason($reason),
            ]);

            if ($restore = $this->disposeThreadIfEmpty($sourceThreadId, (int) $newThread->id)) {
                $op += $restore;
            } else {
                $this->syncThread($sourceThreadId);
            }

            $this->syncThread((int) $newThread->id);

            return $newThread;
        });

        $this->rememberRecentForum($moderator, (int) $destination->id);

        return new RelocationResult(
            destinationUrl: route('threads.show', $newThread->id).'#post-'.$post->id,
            undoToken: $this->mintUndoToken('promote_post', $moderator, [$op]),
            summary: __('forum.relocate.promoted_post', ['thread' => $newThread->title]),
            navigateAway: isset($op['restore_thread']),
        );
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    public function movePostsToThread(Collection $posts, Thread $destination, ?string $reason, User $moderator): RelocationResult
    {
        $ops = [];
        $navigateAway = false;

        DB::transaction(function () use ($posts, $destination, $reason, $moderator, &$ops, &$navigateAway): void {
            $touchedThreadIds = [(int) $destination->id];
            $sourceThreadIds = [];

            foreach ($posts as $post) {
                $sourceThreadId = (int) $post->thread_id;
                $touchedThreadIds[] = $sourceThreadId;
                $sourceThreadIds[] = $sourceThreadId;
                $post->thread_id = $destination->id;
                $post->save();
                $this->repointAttachments((int) $post->id, (int) $destination->id);

                PostModerationLog::query()->create([
                    'thread_id' => $sourceThreadId,
                    'post_id' => $post->id,
                    'moderator_id' => $moderator->id,
                    'action' => 'move',
                    'reason' => $this->trimReason($reason),
                ]);

                $ops[] = ['post_id' => (int) $post->id, 'from_thread_id' => $sourceThreadId];
            }

            $navigateAway = $this->disposeEmptiedSources($sourceThreadIds, (int) $destination->id, $ops);

            foreach (array_unique($touchedThreadIds) as $threadId) {
                $this->syncThread((int) $threadId);
            }
        });

        $this->rememberRecentThread($moderator, (int) $destination->id);

        return new RelocationResult(
            destinationUrl: route('threads.show', $destination->id),
            undoToken: $this->mintUndoToken('move_post', $moderator, $ops),
            summary: trans_choice('forum.relocate.moved_posts', $posts->count(), [
                'count' => $posts->count(),
                'thread' => $destination->title,
            ]),
            count: $posts->count(),
            navigateAway: $navigateAway,
        );
    }

    /**
     * Move several posts into one brand-new thread. The first post becomes
     * the thread starter; the rest are moved in after it.
     *
     * @param  Collection<int, Post>  $posts
     */
    public function promotePostsToThread(Collection $posts, Forum $destination, string $title, ?string $reason, User $moderator): RelocationResult
    {
        $ops = [];
        $navigateAway = false;

        $newThread = DB::transaction(function () use ($posts, $destination, $title, $reason, $moderator, &$ops, &$navigateAway): Thread {
            /** @var Post $first */
            $first = $posts->first();
            $firstSourceThreadId = (int) $first->thread_id;
            $touchedThreadIds = [$firstSourceThreadId];
            $sourceThreadIds = [$firstSourceThreadId];

            $newThread = $this->promotePostToThread($first, $destination, $title);
            $this->repointAttachments((int) $first->id, (int) $newThread->id);

            $ops[] = [
                'post_id' => (int) $first->id,
                'from_thread_id' => $firstSourceThreadId,
                'created_thread_id' => (int) $newThread->id,
            ];
            PostModerationLog::query()->create([
                'thread_id' => $firstSourceThreadId,
                'post_id' => $first->id,
                'moderator_id' => $moderator->id,
                'action' => 'promote',
                'reason' => $this->trimReason($reason),
            ]);

            foreach ($posts->slice(1) as $post) {
                $sourceThreadId = (int) $post->thread_id;
                $touchedThreadIds[] = $sourceThreadId;
                $sourceThreadIds[] = $sourceThreadId;
                $post->thread_id = $newThread->id;
                $post->save();
                $this->repointAttachments((int) $post->id, (int) $newThread->id);

                $ops[] = ['post_id' => (int) $post->id, 'from_thread_id' => $sourceThreadId];
                PostModerationLog::query()->create([
                    'thread_id' => $sourceThreadId,
                    'post_id' => $post->id,
                    'moderator_id' => $moderator->id,
                    'action' => 'move',
                    'reason' => $this->trimReason($reason),
                ]);
            }

            $navigateAway = $this->disposeEmptiedSources($sourceThreadIds, (int) $newThread->id, $ops);

            $touchedThreadIds[] = (int) $newThread->id;
            foreach (array_unique($touchedThreadIds) as $threadId) {
                $this->syncThread((int) $threadId);
            }

            return $newThread;
        });

        $this->rememberRecentForum($moderator, (int) $destination->id);

        return new RelocationResult(
            destinationUrl: route('threads.show', $newThread->id),
            undoToken: $this->mintUndoToken('promote_post', $moderator, $ops),
            summary: trans_choice('forum.relocate.promoted_posts', $posts->count(), [
                'count' => $posts->count(),
                'thread' => $title,
            ]),
            count: $posts->count(),
            navigateAway: $navigateAway,
        );
    }

    /**
     * Reverse a relocation. Returns null when the token is unknown/expired
     * (already used, or older than the TTL).
     */
    public function undo(string $token, User $moderator): ?RelocationResult
    {
        if ($token === '') {
            return null;
        }

        $cacheKey = "moderation:undo:{$token}";
        $payload = Cache::get($cacheKey);
        if (! is_array($payload) || (int) ($payload['mover_id'] ?? 0) !== (int) $moderator->id) {
            return null;
        }

        // Serialize concurrent undo attempts on the same token.
        // TTL comfortably above the worst-case bulk-undo transaction so the
        // lock can't lapse mid-reversal and let a second attempt double-apply.
        // (Needs a lock-capable cache store — redis / database / array / memcached,
        // all of which this app supports; the "file" store does not.)
        $lock = Cache::lock("moderation:undo-lock:{$token}", 120);
        if (! $lock->get()) {
            return null;
        }

        try {
            $payload = Cache::get($cacheKey);
            if (! is_array($payload) || (int) ($payload['mover_id'] ?? 0) !== (int) $moderator->id) {
                return null;
            }

            $result = $this->applyUndo($payload);

            // Consume the token only once the reversal has committed — a
            // failure above throws and leaves it valid for a retry.
            Cache::forget($cacheKey);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyUndo(array $payload): RelocationResult
    {
        $type = (string) ($payload['type'] ?? '');
        $ops = (array) ($payload['ops'] ?? []);
        $touchedForumIds = [];
        $touchedThreadIds = [];
        $recreatedThreadIds = [];
        $count = 0;

        DB::transaction(function () use ($type, $ops, &$touchedForumIds, &$touchedThreadIds, &$recreatedThreadIds, &$count): void {
            $createdThreadIds = [];

            foreach ($ops as $op) {
                if ($type === 'move_thread') {
                    $thread = Thread::query()->find($op['thread_id'] ?? 0);
                    if ($thread instanceof Thread) {
                        $touchedForumIds[] = (int) $thread->forum_id;
                        $touchedForumIds[] = (int) $op['from_forum_id'];
                        $thread->forum_id = (int) $op['from_forum_id'];
                        $thread->save();
                        $count++;
                    }

                    continue;
                }

                // post-level ops: move_post / promote_post / batch. Handle the
                // thread bookkeeping first — it must not depend on the post row
                // still existing.
                $fromThreadId = (int) ($op['from_thread_id'] ?? 0);
                if ($fromThreadId > 0) {
                    $touchedThreadIds[] = $fromThreadId;
                }
                if (($createdThreadId = (int) ($op['created_thread_id'] ?? 0)) > 0) {
                    $createdThreadIds[] = $createdThreadId;
                }

                $sourceWasDisposed = isset($op['restore_thread']) && is_array($op['restore_thread']) && $fromThreadId > 0;
                if ($sourceWasDisposed && ! Thread::query()->whereKey($fromThreadId)->exists()) {
                    $this->recreateThread(
                        $op['restore_thread'],
                        (array) ($op['restore_subscriber_ids'] ?? []),
                        (array) ($op['restore_thread_logs'] ?? []),
                    );
                    $touchedForumIds[] = (int) ($op['restore_thread']['forum_id'] ?? 0);
                    $recreatedThreadIds[] = $fromThreadId;
                }

                $post = Post::query()->find($op['post_id'] ?? 0);
                if (! $post instanceof Post) {
                    continue;
                }

                $currentThreadId = (int) $post->thread_id;
                $touchedThreadIds[] = $currentThreadId;
                $post->thread_id = $fromThreadId;
                $post->save();
                $this->repointAttachments((int) $post->id, $fromThreadId);
                $count++;

                // disposeThreadIfEmpty() swept this post's logs / reports onto
                // the destination — bring them back with the post.
                if ($sourceWasDisposed && $currentThreadId !== $fromThreadId) {
                    PostModerationLog::query()
                        ->where('post_id', $post->id)->where('thread_id', $currentThreadId)
                        ->update(['thread_id' => $fromThreadId]);
                    PostReport::query()
                        ->where('post_id', $post->id)->where('thread_id', $currentThreadId)
                        ->update(['thread_id' => $fromThreadId]);
                }
            }

            // Only once every post is back can we tell whether a thread the
            // original action created is now empty and should be dropped.
            foreach (array_unique($createdThreadIds) as $createdThreadId) {
                if (Post::query()->where('thread_id', $createdThreadId)->exists()) {
                    $touchedThreadIds[] = (int) $createdThreadId;

                    continue;
                }
                $created = Thread::query()->find($createdThreadId);
                if ($created instanceof Thread) {
                    $touchedForumIds[] = (int) $created->forum_id;
                    $created->delete();
                }
            }
        });

        foreach (array_unique($touchedThreadIds) as $threadId) {
            $this->syncThread((int) $threadId);
        }
        if ($touchedForumIds !== []) {
            $this->forumStats->syncForForumIds(array_values(array_unique(array_map('intval', $touchedForumIds))));
        }
        // Re-index recreated threads for search *after* the transaction — a
        // sync engine call here would otherwise block on an external service
        // while holding write locks.
        foreach (array_unique($recreatedThreadIds) as $threadId) {
            $recreated = Thread::query()->find($threadId);
            if ($recreated instanceof Thread) {
                $this->reindex($recreated);
            }
        }

        return new RelocationResult(
            destinationUrl: url()->previous(),
            undoToken: null,
            summary: __('forum.relocate.undo_done'),
            count: $count,
        );
    }

    /**
     * Bring back a source thread that disposeThreadIfEmpty() removed, plus its
     * subscriber + thread-log rows. Runs inside the undo transaction; search
     * re-indexing is done by the caller after commit.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<int>  $subscriberIds
     * @param  list<array<string, mixed>>  $threadLogs
     */
    private function recreateThread(array $snapshot, array $subscriberIds, array $threadLogs): void
    {
        // A brand-new thread may have taken the slug during the undo window.
        $slug = (string) ($snapshot['slug'] ?? '');
        if ($slug !== '' && Thread::query()->where('slug', $slug)->exists()) {
            $snapshot['slug'] = mb_substr($slug, 0, 240).'-'.Str::lower(Str::random(8));
        }

        Thread::query()->insert($snapshot);

        $threadId = (int) ($snapshot['id'] ?? 0);
        if ($threadId <= 0 || ! Thread::query()->whereKey($threadId)->exists()) {
            return;
        }

        // Restore subscribers, skipping any whose user row is gone (would trip
        // the FK on stricter drivers rather than being ignored).
        $userIds = User::query()
            ->whereIn('id', array_values(array_unique(array_map('intval', $subscriberIds))))
            ->pluck('id');
        $now = now();
        $subRows = $userIds->map(static fn ($userId): array => [
            'thread_id' => $threadId, 'user_id' => (int) $userId, 'created_at' => $now, 'updated_at' => $now,
        ])->all();
        if ($subRows !== []) {
            DB::table('thread_subscriptions')->insertOrIgnore($subRows);
        }

        if ($threadLogs !== []) {
            DB::table('thread_moderation_logs')->insertOrIgnore(array_values($threadLogs));
        }
    }

    /**
     * @return array<int, int> recent thread ids, newest first
     */
    public function recentThreadIds(User $moderator): array
    {
        return (array) Cache::get($this->recentKey('threads', $moderator), []);
    }

    /**
     * @return array<int, int> recent forum ids, newest first
     */
    public function recentForumIds(User $moderator): array
    {
        return (array) Cache::get($this->recentKey('forums', $moderator), []);
    }

    private function rememberRecentThread(User $moderator, int $threadId): void
    {
        $this->pushRecent('threads', $moderator, $threadId);
    }

    private function rememberRecentForum(User $moderator, int $forumId): void
    {
        $this->pushRecent('forums', $moderator, $forumId);
    }

    private function pushRecent(string $kind, User $moderator, int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $key = $this->recentKey($kind, $moderator);
        $ids = array_values(array_unique(array_merge([$id], (array) Cache::get($key, []))));
        Cache::put($key, array_slice($ids, 0, self::RECENT_LIMIT), now()->addDays(30));
    }

    private function recentKey(string $kind, User $moderator): string
    {
        return "moderation:recent:{$kind}:{$moderator->id}";
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     */
    private function mintUndoToken(string $type, User $moderator, array $ops): string
    {
        $token = Str::random(40);
        Cache::put("moderation:undo:{$token}", [
            'type' => $type,
            'mover_id' => (int) $moderator->id,
            'ops' => $ops,
        ], now()->addMinutes(self::UNDO_TTL_MINUTES));

        return $token;
    }

    /**
     * Keep the denormalized post_attachments.thread_id column in step with a
     * post that has just changed threads.
     */
    private function repointAttachments(int $postId, int $threadId): void
    {
        PostAttachment::query()
            ->where('post_id', $postId)
            ->whereNotNull('thread_id')
            ->update(['thread_id' => $threadId]);
    }

    private function trimReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : null;
    }

    /**
     * Recompute a thread's cached post count / last-post pointers and its
     * forum stats. Mirrors ForumController::syncThreadDerivedFields().
     */
    private function syncThread(int $threadId): void
    {
        $thread = Thread::query()->find($threadId);
        if (! $thread instanceof Thread) {
            return;
        }

        $postCount = Post::query()
            ->where('thread_id', $thread->id)
            ->where('is_deleted', false)
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->count();
        $latestPost = Post::query()
            ->where('thread_id', $thread->id)
            ->where('is_deleted', false)
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->latest('created_at')
            ->latest('id')
            ->first(['author_id', 'created_at']);

        $thread->reply_count = max(0, $postCount - 1);
        $thread->last_post_author_id = $latestPost?->author_id;
        $thread->last_post_at = $latestPost?->created_at instanceof CarbonInterface
            ? $latestPost->created_at
            : null;

        StatsSyncScope::withoutThreadSync(fn (): bool => $thread->save());
        $this->forumStats->syncForForumId((int) $thread->forum_id);
    }

    /**
     * Puts a thread back into the search index after it was restored by a raw insert, which fires
     * no model events.
     */
    private function reindex(Thread $thread): void
    {
        $indexer = app(Indexer::class);
        $indexes = app(SearchIndexRegistry::class);

        if ($indexer->isActive() && $indexes->has(ThreadSearchIndex::NAME)) {
            $indexer->sync($indexes->get(ThreadSearchIndex::NAME), $thread->loadMissing('forum'));
        }
    }

    /**
     * A new thread made from an existing post: the post becomes its first post and its author
     * follows it if they follow what they post in. Forum stats are synced by the caller.
     */
    private function promotePostToThread(Post $post, Forum $destinationForum, string $title): Thread
    {
        $author = $post->author;

        $thread = Thread::query()->create([
            'forum_id' => $destinationForum->id,
            'author_id' => $author->id,
            'title' => $title,
            'slug' => $this->threadCreation->uniqueSlug($title),
            'moderation_status' => $post->moderation_status,
            'is_pinned' => false,
            'is_locked' => false,
            'reply_count' => 0,
            'approved_by_id' => $post->approved_by_id,
            'approved_at' => $post->approved_at,
        ]);

        $post->thread_id = $thread->id;
        $post->save();

        if ($this->subscriptions->shouldFollowOnPost($author)) {
            $this->subscriptions->subscribe($author, $thread);
        }

        return $thread;
    }
}
