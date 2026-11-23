<?php

namespace App\Forum;

use App\Models\User;
use Carbon\CarbonInterface;
use Filagora\ForumBugReports\Services\BugReports;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Models\ThreadRead;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The thread lists of the public pages (a forum, today, unread): the query that decides which
 * threads a viewer may see, the counts each row shows, and the per-row indicators (unread,
 * attachments, bug status). Who may see which forum is `ForumAccess`'s answer; this only turns
 * it into SQL for a listing and adds what a row displays.
 */
class ThreadListings
{
    public const FORUM_PAGE_SIZE = 30;

    public function __construct(private readonly ForumAccess $access) {}

    /**
     * Threads in every forum the viewer may see, pinned first then by latest post, with the
     * counts and relations a row needs. Staff acting as staff see every active forum.
     *
     * @return Builder<Thread>
     */
    public function visibleTo(?User $user): Builder
    {
        $thread = Models::class('thread');

        $query = $this->withMetrics(
            $thread::query()->join('forums', 'forums.id', '=', 'threads.forum_id'),
            $user,
            ['forum:id,name,slug,forum_group_id', 'author:id,username,name,email', 'lastPostAuthor:id,username,name,email'],
        )
            // addSelect, not select: withMetrics() has already appended the posts_count,
            // posts_max_id and posts_min_id subquery columns; a plain select() would replace the
            // list and the rows would show no post counts and link to the wrong "latest post".
            ->addSelect('threads.*')
            ->where('forums.is_active', true)
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at');

        if ($this->access->actsAsStaff($user)) {
            return $query;
        }

        $this->access->scopeVisibleThreads($query, $user);

        return $this->restrictToVisibleForums($query, $user);
    }

    /**
     * Narrows a query joined to `threads` (as `threads`) to the forums the viewer may read: every
     * thread in a forum they can see in full, and their own threads in a forum where they see only
     * those. Staff acting as staff are not narrowed.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function restrictToVisibleForums(Builder $query, ?User $user): Builder
    {
        if ($this->access->actsAsStaff($user)) {
            return $query;
        }

        $scope = $this->access->forumScopeFor($user);

        return $query->where(function (Builder $visible) use ($scope, $user): void {
            $visible->whereRaw('1 = 0');

            if ($scope->fullForumIds !== []) {
                $visible->orWhereIn('threads.forum_id', $scope->fullForumIds);
            }

            if ($scope->ownThreadsForumIds !== [] && $user instanceof User) {
                $visible->orWhere(function (Builder $own) use ($scope, $user): void {
                    $own->whereIn('threads.forum_id', $scope->ownThreadsForumIds)
                        ->where('threads.author_id', $user->id);
                });
            }
        });
    }

    /**
     * The thread list of one forum the viewer was already checked against.
     *
     * @return Builder<Thread>
     */
    public function inForum(int $forumId, ?User $user, bool $ownThreadsOnly, bool $hideResolvedBugs = false): Builder
    {
        return $this->withMetrics(
            $this->forumScope($forumId, $user, $ownThreadsOnly, $hideResolvedBugs),
            $user,
            ['author:id,username,name,email', 'lastPostAuthor:id,username,name,email'],
        );
    }

    /**
     * The forum listing's filter and order, without the per-row counts: what `inForum()` lists.
     *
     * @return Builder<Thread>
     */
    private function forumScope(int $forumId, ?User $user, bool $ownThreadsOnly, bool $hideResolvedBugs): Builder
    {
        $thread = Models::class('thread');

        $query = $thread::query()
            ->where('forum_id', $forumId)
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at')
            // A unique tiebreak, so threads sharing both timestamps keep one stable position.
            ->orderByDesc('id');

        $this->access->scopeVisibleThreads($query, $user);

        if ($ownThreadsOnly && $user instanceof User) {
            $query->where('author_id', $user->id);
        }

        if ($hideResolvedBugs) {
            app(BugReports::class)->hideResolved($query);
        }

        return $query;
    }

    /**
     * Where the thread's forum link should land: the forum listing page that holds the thread for
     * this viewer, or the forum's first page when the thread is not in their listing (pending,
     * rejected, or reached by a direct link).
     */
    public function forumUrlFor(Thread $thread, Request $request): string
    {
        $user = $request->user() instanceof User ? $request->user() : null;
        $page = $this->forumPageOf($thread, $user, app(BugReportsFeature::class)->hideResolvedFor($request, $user));

        return route('forums.show', array_filter([
            'forum' => $thread->forum,
            'page' => $page > 1 ? $page : null,
        ]));
    }

    /**
     * The 1-based page of `inForum()->paginate(self::FORUM_PAGE_SIZE)` the thread appears on for
     * this viewer, or 1 when it is not in that listing.
     */
    public function forumPageOf(Thread $thread, ?User $user, bool $hideResolvedBugs = false): int
    {
        $ownThreadsOnly = $this->access->seesOnlyOwnThreads($thread->forum, $user);
        $listing = fn (): Builder => $this->forumScope($thread->forum_id, $user, $ownThreadsOnly, $hideResolvedBugs)->reorder();

        if (! $listing()->whereKey($thread->getKey())->exists()) {
            return 1;
        }

        // Threads sorting before this one: pinned first, then newest last_post_at (nulls last),
        // then newest created_at, then highest id. Mirrors the orderBy chain in forumScope().
        $olderTies = fn (Builder $tie): Builder => $tie->where('created_at', '>', $thread->created_at)
            ->orWhere(fn (Builder $same) => $same->where('created_at', $thread->created_at)->where('id', '>', $thread->id));

        $ahead = $listing()->where(function (Builder $before) use ($thread, $olderTies): void {
            $before->where('is_pinned', '>', (int) $thread->is_pinned)
                ->orWhere(function (Builder $samePin) use ($thread, $olderTies): void {
                    $samePin->where('is_pinned', (int) $thread->is_pinned)
                        ->where(function (Builder $byActivity) use ($thread, $olderTies): void {
                            if ($thread->last_post_at === null) {
                                $byActivity->whereNotNull('last_post_at')
                                    ->orWhere(fn (Builder $tie) => $tie->whereNull('last_post_at')->where($olderTies));

                                return;
                            }

                            $byActivity->where('last_post_at', '>', $thread->last_post_at)
                                ->orWhere(fn (Builder $tie) => $tie->where('last_post_at', $thread->last_post_at)->where($olderTies));
                        });
                });
        })->count();

        return intdiv($ahead, self::FORUM_PAGE_SIZE) + 1;
    }

    /**
     * The counts every row shows, from the posts the viewer may see.
     *
     * @param  Builder<Thread>  $query
     * @param  list<string>  $with
     * @return Builder<Thread>
     */
    public function withMetrics(Builder $query, ?User $user, array $with): Builder
    {
        $visiblePosts = fn (Builder $posts): Builder => $this->access->scopeVisiblePosts($posts, $user);

        $query
            ->with($with)
            ->withCount(['posts as posts_count' => $visiblePosts])
            ->withMax(['posts as posts_max_id' => $visiblePosts], 'id')
            ->withMin(['posts as posts_min_id' => $visiblePosts], 'id');

        if ($user instanceof User) {
            $own = function (Builder $posts) use ($user): void {
                $this->access->scopeVisiblePosts($posts, $user)
                    ->where('posts.author_id', $user->id)
                    ->where('posts.is_deleted', false);
            };

            $query
                ->withCount(['posts as user_posts_count' => $own])
                ->withMax(['posts as user_posts_latest_at' => $own], 'created_at');
        }

        return $query;
    }

    /**
     * Sets the per-row indicators on a page of listed threads: unread for this viewer, the
     * attachments they may see, and the latest bug-report status.
     *
     * @param  EloquentCollection<int, Thread>  $threads
     */
    public function hydrateIndicators(EloquentCollection $threads, ?User $user): void
    {
        if ($threads->isEmpty()) {
            return;
        }

        $threadIds = $threads->pluck('id')->map(fn ($value): int => (int) $value)->all();
        $forumIds = $threads->pluck('forum_id')->filter()->map(fn ($value): int => (int) $value)->unique()->values()->all();

        $threadReadsByThreadId = collect();
        $forumReadsByForumId = collect();

        if ($user instanceof User) {
            $threadReadsByThreadId = Models::class('thread_read')::query()
                ->where('user_id', $user->id)
                ->whereIn('thread_id', $threadIds)
                ->get(['thread_id', 'last_read_at', 'is_unread_override'])
                ->keyBy('thread_id');

            if ($forumIds !== []) {
                $forumReadsByForumId = Models::class('forum_read')::query()
                    ->where('user_id', $user->id)
                    ->whereIn('forum_id', $forumIds)
                    ->pluck('last_read_at', 'forum_id');
            }
        }

        $latestBugReportStatusByThreadId = app(BugReportsFeature::class)->latestStatusByThread($threadIds);

        $attachmentsByThreadId = Models::class('post_attachment')::query()
            ->select([
                'post_attachments.id',
                'post_attachments.thread_id',
                'post_attachments.post_id',
                'post_attachments.legacy_attachment_id',
                'post_attachments.original_filename',
            ])
            ->join('posts', 'posts.id', '=', 'post_attachments.post_id')
            ->whereIn('post_attachments.thread_id', $threadIds)
            ->when(! $this->access->actsAsStaff($user), fn (Builder $query) => $query->where('posts.is_deleted', false))
            ->tap(fn (Builder $query) => $this->restrictToVisiblePosts($query, $user))
            ->orderBy('post_attachments.post_id')
            ->orderBy('post_attachments.id')
            ->get()
            ->groupBy(fn ($attachment): int => (int) $attachment->thread_id);

        foreach ($threads as $thread) {
            $effectiveReadAt = $user?->forum_read_baseline_at;
            $isUnreadOverride = false;

            if ($user instanceof User) {
                /** @var ThreadRead|null $threadRead */
                $threadRead = $threadReadsByThreadId->get((int) $thread->id);
                $forumReadAt = $forumReadsByForumId->get((int) $thread->forum_id);
                $isUnreadOverride = (bool) $threadRead?->is_unread_override;

                foreach ([$threadRead?->last_read_at, $forumReadAt] as $readAt) {
                    if ($readAt === null) {
                        continue;
                    }

                    $parsedReadAt = Carbon::parse($readAt);
                    if ($effectiveReadAt === null || $parsedReadAt->gt($effectiveReadAt)) {
                        $effectiveReadAt = $parsedReadAt;
                    }
                }
            }

            $threadActivityAt = $thread->last_post_at ?? $thread->created_at;
            $thread->setAttribute(
                'is_unread_for_user',
                $user instanceof User
                    && $threadActivityAt instanceof CarbonInterface
                    && ($isUnreadOverride || $effectiveReadAt === null || $threadActivityAt->gt($effectiveReadAt))
            );

            $threadAttachments = $attachmentsByThreadId->get((int) $thread->id, new EloquentCollection);
            $thread->setAttribute('has_visible_attachments', $threadAttachments->isNotEmpty());
            $thread->setAttribute('visible_attachments_count', $threadAttachments->count());
            $thread->setRelation('listingAttachments', $threadAttachments->values());
            $thread->setAttribute('latest_bug_report_status', $latestBugReportStatusByThreadId->get((int) $thread->id));
        }
    }

    /**
     * Narrows a query joined to `posts` to the posts the viewer may see (approved, or their own).
     *
     * @param  Builder<Model>  $query
     */
    private function restrictToVisiblePosts(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $visible) use ($user): void {
            $visible->where('posts.moderation_status', ContentModerationStatus::APPROVED->value);

            if ($user instanceof User) {
                $visible->orWhere('posts.author_id', $user->id);
            }
        });
    }

    /**
     * Which of the listed threads this viewer follows.
     *
     * @param  list<int>  $threadIds
     * @return list<int>
     */
    public function subscribedAmong(?User $user, array $threadIds): array
    {
        if (! $user instanceof User || $threadIds === []) {
            return [];
        }

        $subscription = Models::class('thread_subscription');

        return $subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('thread_id', $threadIds)
            ->pluck('thread_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
