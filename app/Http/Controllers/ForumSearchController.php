<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ForumSearchVisibilityResolver;
use App\Support\LegacyTextDecoder;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Search\ForumSearch;
use Filagora\Forums\Search\ForumSearchCriteria;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The forum search: /forums/search lists matching threads with filters, /threads/{thread}/search
 * lists matching posts in one thread. The compact search bar on the forum pages submits here; its
 * scope picks between the two. The engine, its fallback to the database and its circuit breaker
 * belong to the search package; this reads the request and renders.
 */
class ForumSearchController extends Controller
{
    /** Meilisearch stops counting at its default pagination.maxTotalHits, so a total at this value means "at least this many". */
    public const COUNTED_RESULTS_CAP = 1000;

    /** Query parameters a thread-scoped search carries to threads.search. */
    private const THREAD_SCOPE_PARAMS = ['q', 'author', 'since', 'mine', 'post_sort', 'include_unapproved'];

    public function __construct(
        private readonly ForumSearch $search,
        private readonly ForumAccess $access,
        private readonly ForumSearchVisibilityResolver $visibilityResolver,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user() instanceof User ? $request->user() : null;
        $scope = $this->param($request, 'in');

        if (preg_match('/^thread:(\d+)$/', $scope, $threadScope) === 1) {
            $thread = Models::class('thread')::query()->find((int) $threadScope[1]);

            if ($thread !== null) {
                return redirect()->route('threads.search', [
                    'thread' => $thread,
                    ...array_filter(
                        array_map(fn (string $key): string => $this->param($request, $key), array_combine(self::THREAD_SCOPE_PARAMS, self::THREAD_SCOPE_PARAMS)),
                        static fn (string $value): bool => $value !== '',
                    ),
                ]);
            }
        }

        $forumId = preg_match('/^forum:(\d+)$/', $scope, $forumScope) === 1
            ? (int) $forumScope[1]
            : (ctype_digit($this->param($request, 'forum')) ? (int) $this->param($request, 'forum') : null);
        $activeForum = $forumId !== null ? Models::class('forum')::query()->find($forumId) : null;

        if ($activeForum !== null && ! $this->access->canViewForum($activeForum, $user)) {
            $activeForum = null;
        }

        [$authorId, $authorName, $authorNotFound] = $this->resolveAuthor($request);
        $criteria = new ForumSearchCriteria(
            query: $this->normalizeQuery($request),
            forumId: $activeForum?->id,
            authorId: $authorId,
            authorMode: in_array($this->param($request, 'author_mode'), ForumSearchCriteria::AUTHOR_MODES, true) ? $this->param($request, 'author_mode') : 'started',
            since: $this->since($request),
            minReplies: max(0, min(100000, (int) $this->param($request, 'min_replies'))),
            mineUserId: $user instanceof User && $request->boolean('mine') ? (int) $user->id : null,
            sort: in_array($this->param($request, 'sort'), ForumSearchCriteria::SORTS, true) ? $this->param($request, 'sort') : 'last_post',
            includeUnapproved: $this->visibilityResolver->canIncludeUnapproved($user) && $request->boolean('include_unapproved'),
        );

        $hasCriteria = $criteria->query !== ''
            || $criteria->forumId !== null
            || $authorName !== ''
            || $criteria->since !== null
            || $criteria->minReplies > 0
            || $criteria->mineUserId !== null;

        $results = null;
        $unapproved = collect();

        if ($hasCriteria && ! $authorNotFound) {
            $results = $this->search->threads($criteria, $user, max(1, (int) $this->param($request, 'page')));

            if ($criteria->includeUnapproved) {
                $unapproved = $this->search->unapproved($criteria);
            }
        }

        return view('forum.search', [
            'criteria' => $criteria,
            'results' => $results,
            'unapprovedThreads' => $unapproved,
            'activeForum' => $activeForum,
            'authorName' => $authorName,
            'authorNotFound' => $authorNotFound,
            'usedDatabaseFallback' => false,
            'searchUnavailable' => false,
            'canIncludeUnapproved' => $this->visibilityResolver->canIncludeUnapproved($user),
        ]);
    }

    /**
     * The search bar's "Find a forum" list: every forum the viewer can search, grouped. Fetched
     * only when someone opens the scope popover, so rendering forum pages does not pay for it.
     */
    public function forumOptions(Request $request): JsonResponse
    {
        $user = $request->user() instanceof User ? $request->user() : null;

        $options = $this->visibilityResolver->scopeOptionsFor($user)
            ->flatMap(fn (array $group) => $group['forums']->map(fn (object $forum): array => [
                'id' => (int) $forum->id,
                'name' => LegacyTextDecoder::decode((string) $forum->name),
                'group' => LegacyTextDecoder::decode($group['group']),
            ]))
            ->values();

        return response()
            ->json(['data' => $options])
            // Per viewer (guest, member, staff, staff view on or off) at one URL, so never cached:
            // a stored copy would outlive a logout.
            ->header('Cache-Control', 'no-store, private');
    }

    public function thread(Request $request, Thread $thread): View
    {
        $user = $request->user() instanceof User ? $request->user() : null;
        $thread->loadMissing('forum');

        abort_unless($this->access->canViewForum($thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($thread, $user), 403);

        [$authorId, $authorName, $authorNotFound] = $this->resolveAuthor($request);
        $criteria = new ForumSearchCriteria(
            query: $this->normalizeQuery($request),
            authorId: $authorId,
            since: $this->since($request),
            mineUserId: $user instanceof User && $request->boolean('mine') ? (int) $user->id : null,
            includeUnapproved: $this->visibilityResolver->canIncludeUnapproved($user) && $request->boolean('include_unapproved'),
            postSort: in_array($this->param($request, 'post_sort'), ForumSearchCriteria::POST_SORTS, true) ? $this->param($request, 'post_sort') : 'oldest',
        );

        $hasCriteria = $criteria->query !== ''
            || $authorName !== ''
            || $criteria->since !== null
            || $criteria->mineUserId !== null;

        $results = $hasCriteria && ! $authorNotFound
            ? $this->search->posts(
                $this->visiblePostsFor($thread, $user),
                $criteria,
                ! $criteria->includeUnapproved,
                max(1, (int) $this->param($request, 'page')),
                ForumController::THREAD_POSTS_PER_PAGE,
            )
            : null;

        return view('thread.search', [
            'thread' => $thread,
            'criteria' => $criteria,
            'results' => $results,
            'authorName' => $authorName,
            'authorNotFound' => $authorNotFound,
            'canIncludeUnapproved' => $this->visibilityResolver->canIncludeUnapproved($user),
        ]);
    }

    /**
     * The thread page's own post set for this viewer (ForumController::showThread).
     *
     * @return Builder<Post>
     */
    private function visiblePostsFor(Thread $thread, ?User $user): Builder
    {
        $posts = Models::class('post')::query()->where('posts.thread_id', $thread->id);

        if ($this->access->actsAsStaff($user)) {
            return $posts;
        }

        return $this->access->scopeVisiblePosts($posts->where('posts.is_deleted', false), $user);
    }

    /**
     * Exact username, ignoring case. MariaDB's collation already compares case-insensitively, so a
     * plain equality keeps the unique username index; LOWER() would scan every user.
     *
     * @return array{0: ?int, 1: string, 2: bool} [user id, typed name, not found]
     */
    private function resolveAuthor(Request $request): array
    {
        $name = trim($this->param($request, 'author'));

        if ($name === '') {
            return [null, '', false];
        }

        $authorId = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? User::query()->where('username', $name)->value('id')
            : User::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($name)])->value('id');

        return [$authorId !== null ? (int) $authorId : null, $name, $authorId === null];
    }

    private function since(Request $request): ?string
    {
        $since = $this->param($request, 'since');

        return array_key_exists($since, ForumSearchCriteria::SINCE_OPTIONS) ? $since : null;
    }

    /**
     * A query parameter as a string. Array-shaped input (?q[]=x) and other non-scalars read as
     * empty rather than failing a string cast.
     */
    private function param(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function normalizeQuery(Request $request): string
    {
        $query = html_entity_decode(strip_tags($this->param($request, 'q')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $query)), 0, 160);
    }
}
