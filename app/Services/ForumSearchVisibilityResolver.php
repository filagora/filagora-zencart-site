<?php

namespace App\Services;

use App\Models\User;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Abilities;
use Filagora\Forums\Support\ForumViewerScope;
use Filagora\Forums\Support\Models;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Which forums a viewer can search, from the same `ForumAccess` rules as the thread page. Feeds
 * the forum list in the search bar's scope selector (which renders on every forum page, so the
 * forum directory is cached) and the "include unapproved" switch.
 */
class ForumSearchVisibilityResolver
{
    private const DIRECTORY_CACHE_KEY = 'forums:search-directory:v1';

    private const DIRECTORY_CACHE_TTL_SECONDS = 300;

    /** @var array<string, ForumViewerScope> */
    private array $scopeByViewer = [];

    /** @var Collection<int, object>|null */
    private ?Collection $directory = null;

    public function __construct(private readonly ForumAccess $access) {}

    public function visibilityFor(?User $user): ForumViewerScope
    {
        $key = $user instanceof User ? 'user:'.$user->id : 'guest';

        return $this->scopeByViewer[$key] ??= $this->access->forumScopeFor($user);
    }

    /**
     * "Include unapproved" needs the permission and the staff view: with the staff view off the
     * thread page hides unapproved content from moderators too, so the search must not show it.
     */
    public function canIncludeUnapproved(?User $user): bool
    {
        return $user instanceof User
            && $this->access->actsAsStaff($user)
            && Gate::forUser($user)->allows(Abilities::VIEW_UNAPPROVED);
    }

    /**
     * The viewer's searchable forums, grouped for the scope selector's optgroups, in the forum
     * index's own order.
     *
     * @return Collection<int, array{group: string, forums: Collection<int, object>}>
     */
    public function scopeOptionsFor(?User $user): Collection
    {
        $scope = $this->visibilityFor($user);
        $visibleIds = $scope->unrestricted ? null : array_flip([...$scope->fullForumIds, ...$scope->ownThreadsForumIds]);

        return $this->directory()
            ->filter(fn (object $forum): bool => $visibleIds === null || isset($visibleIds[(int) $forum->id]))
            ->groupBy('forum_group_id')
            ->map(fn (Collection $forums): array => ['group' => (string) $forums->first()->group_name, 'forums' => $forums->values()])
            ->values();
    }

    public static function flushDirectory(): void
    {
        Cache::forget(self::DIRECTORY_CACHE_KEY);
    }

    /**
     * Every active forum in an active group, in forum index order: one query, cached until the
     * forum index cache is flushed or the time lapses.
     *
     * @return Collection<int, object>
     */
    private function directory(): Collection
    {
        if ($this->directory !== null) {
            return $this->directory;
        }

        $forumTable = (new (Models::class('forum')))->getTable();
        $groupTable = (new (Models::class('forum_group')))->getTable();

        $rows = Cache::remember(self::DIRECTORY_CACHE_KEY, self::DIRECTORY_CACHE_TTL_SECONDS, fn (): array => Models::class('forum')::query()
            ->join($groupTable, "{$groupTable}.id", '=', "{$forumTable}.forum_group_id")
            ->where("{$forumTable}.is_active", true)
            ->where("{$groupTable}.is_active", true)
            ->orderBy("{$groupTable}.sort_order")
            ->orderBy("{$groupTable}.id")
            ->orderBy("{$forumTable}.sort_order")
            ->orderBy("{$forumTable}.id")
            ->toBase()
            ->get(["{$forumTable}.id", "{$forumTable}.forum_group_id", "{$forumTable}.name", "{$groupTable}.name as group_name"])
            ->map(fn (object $row): array => (array) $row)
            ->all());

        return $this->directory = collect($rows)->map(fn (array $row): object => (object) $row);
    }
}
