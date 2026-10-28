<?php

namespace App\Search;

use App\Forum\PostLinks;
use App\Http\Controllers\ForumController;
use App\Models\User;
use App\Plugins\PluginSearcher;
use Carbon\CarbonInterface;
use Filagora\Cms\Search\BlockText;
use Filagora\Cms\Search\CmsSearch;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Search\ForumSearch;
use Filagora\Forums\Search\ForumSearchCriteria;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models as ForumModels;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Services\ReleaseSelection;
use Filagora\Search\Enums\MatchingStrategy;
use Filagora\Search\Filters\Equals;
use Filagora\Search\Search;
use Filagora\Search\Support\SearchQuery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What the site-wide search page shows: a few of the best matches from each of the forums, threads,
 * plugins and CMS pages the viewer may see. Each kind is the search of the package that owns it
 * (visibility and the engine's fallback to the database are theirs). Forums, threads and pages ask
 * for the engine's looser word matching and score a preload of candidates the way the old site did
 * (`Relevance`) before keeping the top few; plugins keep the plugin package's own ranking.
 *
 * @phpstan-type Item array{title: string, url: string, note?: string, excerpt?: ?string, meta?: list<array{text: string, url: ?string}>, encapsulated_badge?: ?string, supported_versions?: list<string>}
 * @phpstan-type Section array{title: string, source: string, items: list<Item>}
 */
class SiteSearch
{
    public const MINIMUM_QUERY_LENGTH = 2;

    private const SECTION_RESULT_LIMIT = 8;

    /** How many candidates the engine hands back to be scored before the top few are kept. */
    private const PRELOAD_LIMIT = 40;

    public function __construct(
        private readonly ForumAccess $access,
        private readonly ForumSearch $threads,
        private readonly PluginSearcher $plugins,
        private readonly CmsSearch $pages,
        private readonly ReleaseSelection $releases,
        private readonly PostLinks $links,
        private readonly Search $search,
        private readonly BlockText $blockText,
    ) {}

    /**
     * Strips markup, collapses whitespace and cuts the query to what the page accepts.
     */
    public function normalize(string $query): string
    {
        $normalized = html_entity_decode(strip_tags($query), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';

        return trim(mb_substr($normalized, 0, 160));
    }

    /**
     * @return list<Section>
     */
    public function sections(string $query, ?User $viewer, bool $forums = true, bool $plugins = true): array
    {
        $sections = $this->emptySections();

        if ($forums) {
            $sections[SearchResultType::Forum->sectionIndex()]['items'] = $this->forumItems($query, $viewer);
            $sections[SearchResultType::Thread->sectionIndex()]['items'] = $this->threadItems($query, $viewer);
        }

        if ($plugins) {
            $sections[SearchResultType::PluginRelease->sectionIndex()]['items'] = $this->pluginItems($query, $viewer);
        }

        $sections[SearchResultType::CmsPage->sectionIndex()]['items'] = $this->pageItems($query);

        return $sections;
    }

    /**
     * @return list<Section>
     */
    public function emptySections(): array
    {
        return array_map(
            fn (SearchResultType $type): array => ['title' => $type->sectionTitle(), 'source' => $type->sourceLabel(), 'items' => []],
            SearchResultType::cases(),
        );
    }

    /**
     * @return list<Item>
     */
    private function forumItems(string $query, ?User $viewer): array
    {
        $authored = $this->access->authoredForumIds($viewer);
        $tokens = Relevance::tokens($query);

        $result = $this->search->search(ForumSearchIndex::NAME, new SearchQuery(
            text: $query,
            filter: new Equals('is_active', true),
            perPage: self::PRELOAD_LIMIT,
            matching: MatchingStrategy::Last,
        ));

        $rows = ForumModels::class('forum')::query()->whereIn('id', $result->ids() ?: [0])->get()->keyBy(fn ($forum): string => (string) $forum->getKey());

        return collect($result->ids())
            ->map(fn (int|string $id) => $rows->get((string) $id))
            ->filter(fn ($forum): bool => $forum !== null && $forum->is_active && $this->access->canViewForum($forum, $viewer, $authored))
            ->sortByDesc(fn ($forum): int => Relevance::score((string) $forum->name, $tokens, 12) + Relevance::score((string) ($forum->description ?? ''), $tokens, 4))
            ->take(self::SECTION_RESULT_LIMIT)
            ->map(fn ($forum): array => [
                'title' => (string) $forum->name,
                'url' => route('forums.show', $forum),
                'note' => Str::limit((string) ($forum->description ?? ''), 180),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<Item>
     */
    private function threadItems(string $query, ?User $viewer): array
    {
        $tokens = Relevance::tokens($query);
        $criteria = new ForumSearchCriteria(query: $query, sort: 'relevance', matching: MatchingStrategy::Last);

        $found = $this->threads->threads($criteria, $viewer)->getCollection()
            ->concat($this->threads->threads($criteria, $viewer, 2)->getCollection())
            ->sortByDesc(fn ($thread): int => Relevance::score((string) $thread->title, $tokens, 12)
                + Relevance::score((string) ($thread->forum?->name ?? ''), $tokens, 5)
                + Relevance::freshnessBonus($thread->last_post_at ?? $thread->created_at))
            ->take(self::SECTION_RESULT_LIMIT)
            ->values();
        $matching = $this->matchingPosts($found->pluck('id')->all(), $query);

        return $found->map(function ($thread) use ($matching): array {
            $post = $matching->get((int) $thread->getKey());

            return [
                'title' => (string) $thread->title,
                'url' => $post !== null ? $this->postUrl($thread, $post) : route('threads.show', $thread),
                'meta' => $this->threadMeta($thread),
                'excerpt' => $post !== null ? Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $post->body))), 180) : null,
            ];
        })->values()->all();
    }

    /**
     * The first post of each thread that holds the query, in one query for all of them. LIKE on
     * purpose, even where a FULLTEXT index exists: MATCH resolves through an index that knows
     * nothing of thread_id, so it would read every matching post in the forum (6.1 s cold for
     * "email" in production) before the few thread ids narrowed anything; LIKE reads only these
     * threads' posts. Corpus-wide search still uses the engine.
     *
     * @param  list<int|string>  $threadIds
     * @return Collection<int, object>
     */
    private function matchingPosts(array $threadIds, string $query): Collection
    {
        if ($threadIds === []) {
            return collect();
        }

        return ForumModels::class('post')::query()
            ->whereIn('thread_id', $threadIds)
            ->where('is_deleted', false)
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->where('body', 'like', '%'.$this->escapeLike($query).'%')
            ->orderBy('id')
            ->get()
            ->unique('thread_id')
            ->keyBy(fn ($post): int => (int) $post->thread_id);
    }

    private function postUrl(object $thread, object $post): string
    {
        return $this->links->urlFor($thread, $post);
    }

    private function threadPageUrl(object $thread, int $page): string
    {
        return $this->links->pageUrl($thread, $page);
    }

    /**
     * @return list<array{text: string, url: ?string}>
     */
    private function threadMeta(object $thread): array
    {
        $separator = ['text' => ' · ', 'url' => null];
        $tokens = [['text' => 'In forum: ', 'url' => null], ['text' => (string) ($thread->forum?->name ?? 'Unknown forum'), 'url' => null]];

        $started = $thread->created_at;
        $tokens[] = $separator;
        $tokens[] = ['text' => 'Started', 'url' => null];

        if ($thread->author instanceof User) {
            $tokens[] = ['text' => ' by ', 'url' => null];
            $tokens[] = ['text' => (string) $thread->author->username, 'url' => route('members.show', ['user' => $thread->author->id])];
        }

        if ($started instanceof CarbonInterface) {
            $tokens[] = ['text' => ' on ', 'url' => null];
            $tokens[] = ['text' => $started->format('d M Y'), 'url' => route('threads.show', $thread)];
        }

        $replies = (int) ($thread->reply_count ?? 0);
        $tokens[] = $separator;
        $tokens[] = ['text' => $replies === 1 ? '1 reply' : number_format($replies).' replies', 'url' => null];

        if (is_numeric($thread->view_count)) {
            $tokens[] = $separator;
            $tokens[] = ['text' => number_format((int) $thread->view_count).' views', 'url' => null];
        }

        $activity = $thread->last_post_at ?? $thread->created_at;

        if ($activity instanceof CarbonInterface) {
            $tokens[] = $separator;
            $tokens[] = ['text' => 'Last post ', 'url' => null];
            $tokens[] = ['text' => $activity->format('d M Y'), 'url' => $this->threadPageUrl($thread, (int) ceil(($replies + 1) / ForumController::THREAD_POSTS_PER_PAGE))];

            if ($thread->lastPostAuthor instanceof User) {
                $tokens[] = ['text' => ' by ', 'url' => null];
                $tokens[] = ['text' => (string) $thread->lastPostAuthor->username, 'url' => route('members.show', ['user' => $thread->lastPostAuthor->id])];
            }
        }

        return $tokens;
    }

    /**
     * @return list<Item>
     */
    private function pluginItems(string $query, ?User $viewer): array
    {
        // A token that reads as a Zen Cart version ("1.5.7") narrows to plugins that run on it, so
        // "paypal 1.5.7" finds what works there; the rest of the words are searched.
        $tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $versions = array_values(array_filter($tokens, fn (string $token): bool => preg_match('/^\d+\.\d+(?:\.\d+)?$/', $token) === 1));
        $words = trim(implode(' ', array_diff($tokens, $versions)));

        $found = $this->plugins->paginate($words, $versions[0] ?? '', null, 'all', 'relevance', 1, $viewer)->getCollection()->take(self::SECTION_RESULT_LIMIT);
        (new EloquentCollection($found->all()))->loadMissing('releases.zcVersions');

        return $found->map(function (Plugin $plugin): ?array {
            $release = $this->releases->latest($plugin->releases->where('status', 'active'));

            if ($release === null) {
                return null;
            }

            $meta = [];
            $touched = $release->approved_at ?? $release->created_at;

            if ($touched instanceof CarbonInterface) {
                $meta[] = 'Updated '.$touched->format('d M Y');
            }

            $meta[] = number_format((int) ($release->download_count ?? 0)).' downloads';
            $summary = trim((string) Str::limit((string) ($release->changelog ?? ''), 180));

            if ($summary !== '') {
                $meta[] = $summary;
            }

            return [
                'title' => trim(trim((string) $plugin->name).' '.trim((string) $release->version)),
                'url' => route('plugins.show', $plugin),
                'note' => implode(' · ', $meta),
                'encapsulated_badge' => ($release->encapsulated?->value ?? null) === 'yes' ? 'Encapsulated' : null,
                'supported_versions' => $release->zcVersions->pluck('version')->filter(fn ($version): bool => is_string($version) && trim($version) !== '')->values()->all(),
            ];
        })->filter()->values()->all();
    }

    /**
     * @return list<Item>
     */
    private function pageItems(string $query): array
    {
        $tokens = Relevance::tokens($query);

        return $this->pages->pages($query, 1, self::PRELOAD_LIMIT, MatchingStrategy::Last)->getCollection()
            ->sortByDesc(fn ($page): int => Relevance::score((string) $page->title, $tokens, 12)
                + Relevance::score((string) ($page->excerpt ?? ''), $tokens, 5)
                + Relevance::score((string) ($page->seo_title ?? ''), $tokens, 4)
                + Relevance::score((string) ($page->seo_description ?? ''), $tokens, 3)
                + Relevance::score($this->blockText->forPage($page), $tokens, 2))
            ->take(self::SECTION_RESULT_LIMIT)
            ->map(fn ($page): array => [
                'title' => (string) $page->title,
                'url' => route('cms.pages.show', ['slug' => $page->slug]),
                'note' => Str::limit((string) ($page->excerpt ?? $page->seo_description ?? ''), 180),
            ])->values()->all();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
