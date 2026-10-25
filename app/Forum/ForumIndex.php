<?php

namespace App\Forum;

use App\Models\User;
use App\Support\CacheMetrics;
use App\Support\ForumsIndexCache;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models;
use Illuminate\Support\Facades\Cache;

/**
 * What the forum index page shows: the groups and forums the viewer may see, and for each forum
 * its thread and post counts and its last post. The counts come from the forums' own maintained
 * columns, so nothing here counts rows; only a forum where the viewer sees just their own
 * threads is counted from the data. The result is cached for the kinds of viewer that share one.
 *
 * @phpstan-type LastPost array{title:string, author:string, authorId:?int, at:?string, threadId:int, postId:int}
 * @phpstan-type Meta array{threadCount:int, postCount:int, lastPost: LastPost|null}
 */
class ForumIndex
{
    public function __construct(private readonly ForumAccess $access) {}

    /**
     * @return array{groupRows: list<array{group: object, forums: list<object>}>, forumMeta: array<int, Meta>, totals: array{groups:int,forums:int,threads:int,posts:int}}
     */
    public function for(?User $user): array
    {
        $groups = Models::class('forum_group')::query()
            ->where('is_active', true)
            ->with(['forums' => function ($query): void {
                // No withCount('threads'): it compiles to a correlated COUNT(*) per forum and made
                // this page examine every thread on every request. forums.thread_count is the
                // same number, kept up to date on write.
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $authoredForumIds = $this->access->authoredForumIds($user);

        $forumIds = [];
        $groupRows = [];
        $seesEntitlementForum = false;

        foreach ($groups as $group) {
            $visibleForums = [];

            foreach ($group->forums as $forum) {
                if (! $this->access->canViewForum($forum, $user, $authoredForumIds)) {
                    continue;
                }

                $forumIds[] = (int) $forum->id;
                $visibleForums[] = $forum;
                $seesEntitlementForum = $seesEntitlementForum || $forum->visibility === ForumVisibility::ENTITLEMENT_ONLY;
            }

            if ($visibleForums !== []) {
                $groupRows[] = ['group' => $group, 'forums' => $visibleForums];
            }
        }

        [$forumMeta, $totals] = $this->metaFor($forumIds, $user, $this->cacheScope($user, $authoredForumIds, $seesEntitlementForum));

        $totals['groups'] = count($groupRows);
        $totals['forums'] = count($forumIds);

        return ['groupRows' => $groupRows, 'forumMeta' => $forumMeta, 'totals' => $totals];
    }

    /**
     * The kind of viewer this one shares a cached page with; null when what they see is theirs
     * alone (forums they authored in, or one they see only through an entitlement) and must be
     * worked out each time.
     *
     * @param  list<int>  $authoredForumIds
     */
    private function cacheScope(?User $user, array $authoredForumIds, bool $seesEntitlementForum): ?string
    {
        if (! $user instanceof User) {
            return ForumsIndexCache::SCOPE_GUEST;
        }

        if ($this->access->actsAsStaff($user)) {
            return ForumsIndexCache::SCOPE_STAFF;
        }

        if ($authoredForumIds !== [] || $seesEntitlementForum) {
            return null;
        }

        return $user->hasActiveMembership()
            ? ForumsIndexCache::SCOPE_MEMBER
            : ForumsIndexCache::SCOPE_REGISTERED;
    }

    /**
     * @param  list<int>  $forumIds
     * @return array{0: array<int, Meta>, 1: array{groups:int,forums:int,threads:int,posts:int}}
     */
    private function metaFor(array $forumIds, ?User $user, ?string $cacheScope): array
    {
        if ($cacheScope === null) {
            $forumMeta = $this->buildMeta($forumIds, $user);

            return [$forumMeta, $this->totalsFrom($forumMeta)];
        }

        $key = ForumsIndexCache::keyForScope($cacheScope);
        $signature = ForumsIndexCache::signatureForForumIds($forumIds);
        $cached = Cache::get($key);

        if (is_array($cached)
            && ($cached['signature'] ?? null) === $signature
            && is_array($cached['forumMeta'] ?? null)
            && is_array($cached['totals'] ?? null)
        ) {
            CacheMetrics::hit('forums_index', $key);

            return [$cached['forumMeta'], $cached['totals']];
        }

        CacheMetrics::miss('forums_index', $key);
        $forumMeta = $this->buildMeta($forumIds, $user);
        $totals = $this->totalsFrom($forumMeta);

        Cache::put($key, [
            'signature' => $signature,
            'forumMeta' => $forumMeta,
            'totals' => $totals,
            'warmed_at' => now()->toIso8601String(),
        ], now()->addSeconds(ForumsIndexCache::ttlSeconds()));

        return [$forumMeta, $totals];
    }

    /**
     * @param  array<int, Meta>  $forumMeta
     * @return array{groups:int,forums:int,threads:int,posts:int}
     */
    private function totalsFrom(array $forumMeta): array
    {
        $totals = ['groups' => 0, 'forums' => 0, 'threads' => 0, 'posts' => 0];

        foreach ($forumMeta as $meta) {
            $totals['threads'] += (int) ($meta['threadCount'] ?? 0);
            $totals['posts'] += (int) ($meta['postCount'] ?? 0);
        }

        return $totals;
    }

    /**
     * @param  list<int>  $forumIds
     * @return array<int, Meta>
     */
    private function buildMeta(array $forumIds, ?User $user): array
    {
        if ($forumIds === []) {
            return [];
        }

        $forumModel = Models::class('forum');
        $threadModel = Models::class('thread');
        $postModel = Models::class('post');
        $userModel = $user instanceof User ? $user::class : User::class;

        $orderedForumIds = array_values(array_unique(array_map('intval', $forumIds)));
        $meta = [];

        foreach ($orderedForumIds as $forumId) {
            $meta[$forumId] = ['threadCount' => 0, 'postCount' => 0, 'lastPost' => null];
        }

        $forums = $forumModel::query()
            ->whereIn('id', $orderedForumIds)
            ->get(['id', 'visibility', 'thread_count', 'post_count', 'last_post_id', 'last_post_thread_id', 'last_post_author_id', 'last_post_at'])
            ->keyBy('id');

        $scopedForumIds = [];
        $normalForums = [];

        foreach ($orderedForumIds as $forumId) {
            $forum = $forums->get($forumId);

            if ($forum === null) {
                continue;
            }

            if ($user instanceof User && $this->access->seesOnlyOwnThreads($forum, $user)) {
                $scopedForumIds[] = $forumId;
            } else {
                $normalForums[$forumId] = $forum;
            }
        }

        $threadTitlesById = [];
        $authorLabelsById = [];

        if ($normalForums !== []) {
            $threadIds = array_values(array_filter(array_unique(array_map(
                static fn ($forum): int => (int) ($forum->last_post_thread_id ?? 0),
                $normalForums,
            ))));
            $authorIds = array_values(array_filter(array_unique(array_map(
                static fn ($forum): int => (int) ($forum->last_post_author_id ?? 0),
                $normalForums,
            ))));

            if ($threadIds !== []) {
                $threadTitlesById = $threadModel::query()
                    ->whereIn('id', $threadIds)
                    ->pluck('title', 'id')
                    ->mapWithKeys(fn ($title, $threadId): array => [(int) $threadId => (string) $title])
                    ->all();
            }

            if ($authorIds !== []) {
                $authorLabelsById = $userModel::query()
                    ->whereIn('id', $authorIds)
                    ->get(['id', 'username', 'name', 'email'])
                    ->mapWithKeys(fn ($author): array => [
                        (int) $author->id => (string) ($author->username ?: $author->name ?: $author->email ?: 'Unknown'),
                    ])
                    ->all();
            }
        }

        foreach ($normalForums as $forumId => $forum) {
            $threadId = (int) ($forum->last_post_thread_id ?? 0);
            $authorId = (int) ($forum->last_post_author_id ?? 0);
            $postId = (int) ($forum->last_post_id ?? 0);
            $lastPost = null;

            if ($postId > 0 && $threadId > 0) {
                $lastPost = [
                    'title' => (string) ($threadTitlesById[$threadId] ?? ''),
                    'author' => (string) ($authorLabelsById[$authorId] ?? 'Unknown'),
                    'authorId' => $authorId > 0 ? $authorId : null,
                    'at' => $forum->last_post_at?->toDateTimeString(),
                    'threadId' => $threadId,
                    'postId' => $postId,
                ];
            }

            $meta[$forumId] = [
                'threadCount' => (int) ($forum->thread_count ?? 0),
                'postCount' => (int) ($forum->post_count ?? 0),
                'lastPost' => $lastPost,
            ];
        }

        if ($scopedForumIds !== [] && $user instanceof User) {
            $approved = ContentModerationStatus::APPROVED->value;

            $threadCounts = $threadModel::query()
                ->selectRaw('forum_id, COUNT(*) as aggregate')
                ->whereIn('forum_id', $scopedForumIds)
                ->approved()
                ->where('author_id', $user->id)
                ->groupBy('forum_id')
                ->pluck('aggregate', 'forum_id')
                ->mapWithKeys(fn ($count, $forumId): array => [(int) $forumId => (int) $count])
                ->all();

            $postCounts = $postModel::query()
                ->join('threads', 'threads.id', '=', 'posts.thread_id')
                ->selectRaw('threads.forum_id as forum_id, COUNT(posts.id) as aggregate')
                ->whereIn('threads.forum_id', $scopedForumIds)
                ->where('posts.moderation_status', $approved)
                ->where('threads.author_id', $user->id)
                ->groupBy('threads.forum_id')
                ->pluck('aggregate', 'forum_id')
                ->mapWithKeys(fn ($count, $forumId): array => [(int) $forumId => (int) $count])
                ->all();

            $lastPostIdsByForum = $postModel::query()
                ->join('threads', 'threads.id', '=', 'posts.thread_id')
                ->selectRaw('threads.forum_id as forum_id, MAX(posts.id) as last_post_id')
                ->whereIn('threads.forum_id', $scopedForumIds)
                ->where('posts.moderation_status', $approved)
                ->where('threads.author_id', $user->id)
                ->groupBy('threads.forum_id')
                ->pluck('last_post_id', 'forum_id')
                ->mapWithKeys(fn ($postId, $forumId): array => [(int) $forumId => (int) $postId])
                ->all();

            $scopedLastPosts = [];
            $scopedLastPostIds = array_values(array_filter($lastPostIdsByForum));

            if ($scopedLastPostIds !== []) {
                $scopedLastPosts = $postModel::query()
                    ->with(['thread:id,forum_id,title', 'author:id,username,name,email'])
                    ->whereIn('id', $scopedLastPostIds)
                    ->get()
                    ->keyBy('id')
                    ->all();
            }

            foreach ($scopedForumIds as $forumId) {
                $lastPost = null;
                $post = $scopedLastPosts[(int) ($lastPostIdsByForum[$forumId] ?? 0)] ?? null;

                if ($post !== null) {
                    $author = $post->author;
                    $lastPost = [
                        'title' => (string) ($post->thread?->title ?? ''),
                        'author' => (string) ($author?->username ?? $author?->name ?? $author?->email ?? 'Unknown'),
                        'authorId' => $author?->id ? (int) $author->id : null,
                        'at' => $post->created_at?->toDateTimeString(),
                        'threadId' => (int) ($post->thread?->id ?? 0),
                        'postId' => (int) $post->id,
                    ];
                }

                $meta[$forumId] = [
                    'threadCount' => (int) ($threadCounts[$forumId] ?? 0),
                    'postCount' => (int) ($postCounts[$forumId] ?? 0),
                    'lastPost' => $lastPost,
                ];
            }
        }

        $orderedMeta = [];

        foreach ($forumIds as $forumId) {
            if (isset($meta[(int) $forumId])) {
                $orderedMeta[(int) $forumId] = $meta[(int) $forumId];
            }
        }

        return $orderedMeta;
    }
}
