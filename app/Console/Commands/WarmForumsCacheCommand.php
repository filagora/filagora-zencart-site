<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ForumsIndexCache;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Support\Models;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmForumsCacheCommand extends Command
{
    protected $signature = 'forums:warm-cache
        {--flush : Clear existing /forums cache entries before warming}';

    protected $description = 'Warm cache entries used by the /forums index endpoint.';

    public function handle(): int
    {
        if ((bool) $this->option('flush')) {
            ForumsIndexCache::flushAll();
            $this->line('Flushed forums index cache.');
        }

        $this->warmScope(ForumsIndexCache::SCOPE_GUEST);
        $this->warmScope(ForumsIndexCache::SCOPE_REGISTERED);
        $this->warmScope(ForumsIndexCache::SCOPE_MEMBER);
        $this->warmScope(ForumsIndexCache::SCOPE_STAFF);

        $guestKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_GUEST);
        $staffKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_STAFF);
        $registeredKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_REGISTERED);
        $memberKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_MEMBER);
        $this->info('Forums cache warmed.');
        $this->line("Guest key: {$guestKey}");
        $this->line('Guest key status: '.($this->isWarm($guestKey) ? 'warm' : 'missing'));
        $this->line("Staff key: {$staffKey}");
        $this->line('Staff key status: '.($this->isWarm($staffKey) ? 'warm' : 'missing'));
        $this->line("Registered key: {$registeredKey}");
        $this->line('Registered key status: '.($this->isWarm($registeredKey) ? 'warm' : 'missing'));
        $this->line("Member key: {$memberKey}");
        $this->line('Member key status: '.($this->isWarm($memberKey) ? 'warm' : 'missing'));

        return self::SUCCESS;
    }

    private function warmScope(string $scope): void
    {
        $forumIds = $this->resolveVisibleForumIdsForScope($scope);
        $forumMeta = $this->buildForumMetaFromProjection($forumIds, $scope);
        $totals = $this->totalsFromMeta($forumMeta);

        Cache::put(ForumsIndexCache::keyForScope($scope), [
            'signature' => ForumsIndexCache::signatureForForumIds($forumIds),
            'forumMeta' => $forumMeta,
            'totals' => $totals,
            'warmed_at' => now()->toIso8601String(),
        ], now()->addSeconds(ForumsIndexCache::ttlSeconds()));
    }

    /**
     * @return list<int>
     */
    private function resolveVisibleForumIdsForScope(string $scope): array
    {
        $forumGroups = Models::class('forum_group')::query()
            ->where('is_active', true)
            ->with(['forums' => function ($query): void {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $forumIds = [];

        foreach ($forumGroups as $group) {
            foreach ($group->forums as $forum) {
                if (! $this->isVisibleForScope($forum, $scope)) {
                    continue;
                }

                $forumIds[] = (int) $forum->id;
            }
        }

        return array_values(array_unique($forumIds));
    }

    private function isVisibleForScope(Forum $forum, string $scope): bool
    {
        if ($scope === ForumsIndexCache::SCOPE_STAFF) {
            return true;
        }

        return match ($forum->visibility) {
            ForumVisibility::PUBLIC => true,
            ForumVisibility::REGISTERED => $scope !== ForumsIndexCache::SCOPE_GUEST,
            ForumVisibility::STAFF_ONLY => false,
            ForumVisibility::ENTITLEMENT_ONLY => false,
            ForumVisibility::PAID_ONLY => $scope === ForumsIndexCache::SCOPE_MEMBER,
            ForumVisibility::PAID_OR_AUTHOR => $scope !== ForumsIndexCache::SCOPE_GUEST,
            ForumVisibility::STAFF_AUTHOR_ONLY => $scope !== ForumsIndexCache::SCOPE_GUEST
                && (bool) $forum->show_when_empty
                && $this->isForumEmpty($forum),
        };
    }

    private function isForumEmpty(Forum $forum): bool
    {
        return (int) ($forum->thread_count ?? 0) === 0;
    }

    /**
     * @param  list<int>  $forumIds
     * @return array<int, array{threadCount:int, postCount:int, lastPost: array{title:string, author:string, authorId:?int, at:?string, threadId:int, postId:int}|null}>
     */
    private function buildForumMetaFromProjection(array $forumIds, string $scope): array
    {
        if ($forumIds === []) {
            return [];
        }

        $forums = Forum::query()
            ->whereIn('id', $forumIds)
            ->get([
                'id',
                'visibility',
                'thread_count',
                'post_count',
                'last_post_id',
                'last_post_thread_id',
                'last_post_author_id',
                'last_post_at',
            ])
            ->keyBy('id');

        $threadIds = $forums
            ->pluck('last_post_thread_id')
            ->filter(fn ($value): bool => (int) $value > 0)
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $authorIds = $forums
            ->pluck('last_post_author_id')
            ->filter(fn ($value): bool => (int) $value > 0)
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $threadTitlesById = [];
        if ($threadIds !== []) {
            $threadTitlesById = Models::class('thread')::query()
                ->whereIn('id', $threadIds)
                ->pluck('title', 'id')
                ->mapWithKeys(fn ($title, $threadId): array => [(int) $threadId => (string) $title])
                ->all();
        }

        $authorLabelsById = [];
        if ($authorIds !== []) {
            $authorLabelsById = User::query()
                ->whereIn('id', $authorIds)
                ->get(['id', 'username', 'name', 'email'])
                ->mapWithKeys(function (User $author): array {
                    return [
                        (int) $author->id => (string) ($author->username ?: $author->name ?: $author->email ?: 'Unknown'),
                    ];
                })
                ->all();
        }

        $meta = [];
        foreach ($forumIds as $forumId) {
            $forum = $forums->get((int) $forumId);
            if (! $forum instanceof Forum) {
                continue;
            }

            if ($scope === ForumsIndexCache::SCOPE_REGISTERED
                && $forum->visibility === ForumVisibility::PAID_OR_AUTHOR) {
                $meta[(int) $forumId] = [
                    'threadCount' => 0,
                    'postCount' => 0,
                    'lastPost' => null,
                ];

                continue;
            }

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

            $meta[(int) $forumId] = [
                'threadCount' => (int) ($forum->thread_count ?? 0),
                'postCount' => (int) ($forum->post_count ?? 0),
                'lastPost' => $lastPost,
            ];
        }

        return $meta;
    }

    /**
     * @param  array<int, array{threadCount:int, postCount:int, lastPost: array{title:string, author:string, authorId:?int, at:?string, threadId:int, postId:int}|null}>  $forumMeta
     * @return array{groups:int,forums:int,threads:int,posts:int}
     */
    private function totalsFromMeta(array $forumMeta): array
    {
        $totals = [
            'groups' => 0,
            'forums' => 0,
            'threads' => 0,
            'posts' => 0,
        ];

        foreach ($forumMeta as $meta) {
            $totals['threads'] += (int) ($meta['threadCount'] ?? 0);
            $totals['posts'] += (int) ($meta['postCount'] ?? 0);
        }

        return $totals;
    }

    private function isWarm(string $key): bool
    {
        $payload = cache()->get($key);

        return is_array($payload)
            && is_string($payload['signature'] ?? null)
            && is_array($payload['forumMeta'] ?? null)
            && is_array($payload['totals'] ?? null);
    }
}
