<?php

namespace App\Http\Controllers;

use App\Forum\ForumIndex;
use App\Forum\ThreadListings;
use App\Models\BugReport;
use App\Models\User;
use App\Support\CrawlerUserAgent;
use App\Support\ForumStructuredDataFactory;
use App\Support\LegacyTextDecoder;
use App\Support\MetaDescription;
use App\Support\ThreadOpeningText;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\PostReportStatus;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Services\ApprovalRules;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Services\ReadTracking;
use Filagora\Forums\Support\Models;
use Filagora\Plugins\Support\Models as PluginModels;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The public forum pages that only read: the index, a forum, a thread, and the activity lists.
 * Who may see what is `ForumAccess`'s answer; the listings and the index counts are in
 * `ThreadListings` and `ForumIndex`.
 */
class ForumController extends Controller
{
    // Public: ThreadUpdatedNotification reuses this to work out which page a notified post lands
    // on, so its email link can deep-link straight to it.
    public const THREAD_POSTS_PER_PAGE = 20;

    private const THREAD_VIEW_DEDUPE_MINUTES = 30;

    public function __construct(
        private readonly ForumAccess $access,
        private readonly ApprovalRules $approvals,
        private readonly ReadTracking $readTracking,
        private readonly ThreadListings $listings,
        private readonly ForumIndex $forumIndex,
    ) {}

    public function index(Request $request): View
    {
        return view('forum.index', $this->forumIndex->for($this->viewer($request)));
    }

    public function today(Request $request): View
    {
        $user = $this->viewer($request);

        $threads = $this->listings->visibleTo($user)
            // last_activity_at is the greater of last_post_at and created_at, so this is "posted or
            // started in the last day" without an OR across two columns no index could serve.
            ->where('threads.last_activity_at', '>=', now()->subHours(24))
            ->paginate(30)
            ->withQueryString();

        return $this->activity($user, $threads, 'today');
    }

    public function unread(Request $request): View
    {
        $user = $this->viewer($request);
        abort_unless($user instanceof User, 403);
        abort_unless($user->hasVerifiedEmail(), 403);

        // No select() after visibleTo(): it fixed the column list with addSelect() and a plain
        // select would drop the post counts the rows show.
        $threads = $this->readTracking->unreadThreads($user, $this->listings->visibleTo($user))
            ->paginate(30)
            ->withQueryString();

        return $this->activity($user, $threads, 'unread');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->viewer($request);
        abort_unless($user instanceof User, 403);
        abort_unless($user->hasVerifiedEmail(), 403);

        $marked = $this->readTracking->markAllRead($user);

        return redirect()
            ->route('forums.unread')
            ->with('thread_status', $marked === 0
                ? __('forum.flash.mark_all_read_none')
                : __('forum.flash.mark_all_read_success'));
    }

    public function show(Request $request, Forum $forum): View
    {
        $user = $this->viewer($request);

        abort_unless($this->access->canViewForum($forum, $user), 403);

        $ownThreadsOnly = $this->access->seesOnlyOwnThreads($forum, $user);

        $threads = $this->listings->inForum($forum->id, $user, $ownThreadsOnly)
            ->paginate(30)
            ->withQueryString();
        $this->listings->hydrateIndicators($threads->getCollection(), $user);

        $forumName = trim(LegacyTextDecoder::decode((string) $forum->name));
        $forumDescription = MetaDescription::fromMarkup(LegacyTextDecoder::decode((string) $forum->description));
        $viewerForApproval = $this->approvalViewer($user);

        return view('forum.show', [
            'metaDescription' => $forumDescription !== '' ? $forumDescription : 'Zen Cart support forum: '.$forumName.'.',
            'canonicalUrl' => route('forums.show', array_filter([
                'forum' => $forum,
                'page' => $threads->currentPage() > 1 ? $threads->currentPage() : null,
            ])),
            'forum' => $forum,
            'threads' => $threads,
            'subscribedThreadIds' => $this->listings->subscribedAmong($user, $threads->getCollection()->pluck('id')->all()),
            'totals' => $this->forumTotals($forum, $user, $ownThreadsOnly),
            'threadPostsPerPage' => self::THREAD_POSTS_PER_PAGE,
            'canPostThread' => $this->access->canPostInForum($forum, $user),
            'showModerationActions' => $this->access->actsAsStaff($user),
            // Resolved-bug filtering arrives with the bug-report slice (S8).
            'canHideResolvedBugThreads' => false,
            'hideResolvedBugThreads' => false,
            'threadRequiresApproval' => $this->approvals->threadRequiresApproval($forum, $viewerForApproval),
            'replyRequiresApproval' => $this->approvals->replyRequiresApproval($forum, $viewerForApproval),
        ]);
    }

    public function showThread(Request $request, Thread $thread): View
    {
        $thread->load([
            'forum.group',
            'author' => fn ($query) => $query
                ->select(['id', 'username', 'name', 'email', 'user_title', 'location', 'avatar_path', 'created_at', 'legacy_registered_at', 'forum_posts_count']),
        ]);

        $user = $this->viewer($request);

        abort_unless($this->access->canViewForum($thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($thread, $user), 403);

        if ($user instanceof User) {
            $this->readTracking->markThreadRead($user, $thread);
        }

        if ($this->recordThreadView($thread, $user, $request)) {
            $thread->view_count = (int) $thread->view_count + 1;
        }

        $actsAsStaff = $this->access->actsAsStaff($user);
        $canReply = ! $thread->is_locked
            && $thread->isApproved()
            && $this->access->canReplyToThread($thread, $user);
        $canModerateThread = $actsAsStaff && $this->access->canModerate($user);
        $isSubscribed = $user instanceof User
            && Models::class('thread_subscription')::query()
                ->where('user_id', $user->id)
                ->where('thread_id', $thread->id)
                ->exists();

        $posts = Models::class('post')::query()
            ->with([
                'author' => fn ($query) => $query
                    ->select(['id', 'username', 'name', 'email', 'user_title', 'location', 'avatar_path', 'created_at', 'legacy_registered_at', 'forum_posts_count', 'signature']),
                'deletedBy:id,username,name,email',
                'editedBy:id,username,name,email',
                'attachments',
            ])
            ->when(config('features.post_likes'), fn ($query) => $query->withCount([
                'reactions as like_reactions_count' => fn ($reactions) => $reactions->where('type', 'like'),
            ]))
            ->where('thread_id', $thread->id)
            ->when(! $actsAsStaff, fn ($query) => $query->where('is_deleted', false));

        if (! $actsAsStaff) {
            $this->access->scopeVisiblePosts($posts, $user);
        }

        $posts = $posts
            ->orderBy('created_at')
            ->paginate(self::THREAD_POSTS_PER_PAGE)
            ->withQueryString();

        $this->hydrateAuthorMetrics($thread, $posts->getCollection());

        [$reactedPostIds, $reportedPostIds, $openReportPostIds] = $this->postMarkers($user, $posts->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all(), $canModerateThread);

        $moderationLogs = $canModerateThread
            ? Models::class('thread_moderation_log')::query()
                ->with('moderator:id,username,name,email')
                ->where('thread_id', $thread->id)
                ->latest('created_at')
                ->limit(12)
                ->get()
            : collect();
        $postModerationLogs = $canModerateThread
            ? Models::class('post_moderation_log')::query()
                ->with('moderator:id,username,name,email')
                ->where('thread_id', $thread->id)
                ->latest('created_at')
                ->limit(12)
                ->get()
            : collect();

        // Fetched for every visitor so the thread's bug status is publicly visible.
        $bugReports = BugReport::query()
            ->with(['reportedBy:id,username,name', 'assignedTo:id,username,name'])
            ->where('thread_id', $thread->id)
            ->latest('created_at')
            ->get();

        // Fetched for every visitor so the poll (if any) is publicly visible; its results stay
        // hidden until the viewer has voted or may see them, which the view decides.
        $thread->loadMissing(['poll.questions.options' => fn ($query) => $query->withCount('answers')]);

        $openingText = app(ThreadOpeningText::class)->for($thread);

        return view('thread.show', [
            'structuredData' => app(ForumStructuredDataFactory::class)->forThread($thread, $posts->currentPage(), $openingText),
            'metaDescription' => MetaDescription::fromPlainText($openingText),
            'canonicalUrl' => route('threads.show', array_filter([
                'thread' => $thread,
                'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null,
            ])),
            'thread' => $thread,
            'posts' => $posts,
            'postCount' => $posts->total(),
            'canReply' => $canReply,
            'canModerateThread' => $canModerateThread,
            'moderationLogs' => $moderationLogs,
            'postModerationLogs' => $postModerationLogs,
            'isSubscribed' => $isSubscribed,
            'reactedPostIds' => $reactedPostIds,
            'reportedPostIds' => $reportedPostIds,
            'openReportPostIds' => $openReportPostIds,
            'replyRequiresApproval' => $this->approvals->replyRequiresApproval($thread->forum, $this->approvalViewer($user)),
            'bugReports' => $bugReports,
            // Assigning a bug to staff arrives with the bug-report slice (S8).
            'assignableStaff' => collect(),
            'hasVotedInPoll' => $thread->poll?->hasVoted($user) ?? false,
            'pollResultsVisible' => $actsAsStaff,
            'richComposerDefault' => ($user?->prefers_rich_editor ?? true) ? 'rich' : 'source',
        ]);
    }

    private function viewer(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The viewer the forum's approval rules are judged against: staff acting as staff are judged
     * as staff, staff who switched their privileges off as a guest would be, everyone else as
     * themselves.
     */
    private function approvalViewer(?User $user): ?User
    {
        if (! $this->access->isStaff($user)) {
            return $user;
        }

        return $this->access->actsAsStaff($user) ? $user : null;
    }

    /**
     * @param  LengthAwarePaginator<int, \Filagora\Forums\Models\Thread>  $threads
     */
    private function activity(?User $user, $threads, string $kind): View
    {
        $this->listings->hydrateIndicators($threads->getCollection(), $user);

        return view('forum.activity', [
            'pageTitle' => __("forum.activity.{$kind}.title"),
            'pageDescription' => __("forum.activity.{$kind}.description"),
            'threads' => $threads,
            'subscribedThreadIds' => $this->listings->subscribedAmong($user, $threads->getCollection()->pluck('id')->all()),
            'emptyStateTitle' => __("forum.activity.{$kind}.empty_title"),
            'emptyStateDescription' => __("forum.activity.{$kind}.empty_description"),
            'threadPostsPerPage' => self::THREAD_POSTS_PER_PAGE,
        ]);
    }

    /**
     * @return array{threads:int,posts:int}
     */
    private function forumTotals(Forum $forum, ?User $user, bool $ownThreadsOnly): array
    {
        if (! $ownThreadsOnly) {
            return [
                'threads' => (int) ($forum->thread_count ?? 0),
                'posts' => (int) ($forum->post_count ?? 0),
            ];
        }

        $threads = Models::class('thread')::query()->where('forum_id', $forum->id)->approved();
        $posts = Models::class('post')::query()
            ->join('threads', 'threads.id', '=', 'posts.thread_id')
            ->where('threads.forum_id', $forum->id)
            ->where('posts.moderation_status', ContentModerationStatus::APPROVED->value);

        if ($user instanceof User) {
            $threads->where('author_id', $user->id);
            $posts->where('threads.author_id', $user->id);
        }

        return ['threads' => $threads->count(), 'posts' => (int) $posts->count()];
    }

    /**
     * The viewer's reactions and reports on the posts of this page: which they liked, which they
     * have an open report on, and (for moderators) which have any open report.
     *
     * @param  list<int>  $postIds
     * @return array{0: list<int>, 1: list<int>, 2: list<int>}
     */
    private function postMarkers(?User $user, array $postIds, bool $canModerateThread): array
    {
        if (! $user instanceof User || $postIds === []) {
            return [[], [], []];
        }

        $ids = fn ($query): array => $query->pluck('post_id')->map(fn ($id): int => (int) $id)->all();

        $reacted = config('features.post_likes')
            ? $ids(Models::class('reaction')::query()
                ->where('user_id', $user->id)
                ->where('type', 'like')
                ->whereIn('post_id', $postIds))
            : [];

        $reported = $ids(Models::class('post_report')::query()
            ->where('reporter_id', $user->id)
            ->where('status', PostReportStatus::OPEN->value)
            ->whereIn('post_id', $postIds));

        // Which posts have an open report, whoever filed it: only moderators see that strip.
        $open = $canModerateThread
            ? $ids(Models::class('post_report')::query()
                ->where('status', PostReportStatus::OPEN->value)
                ->whereIn('post_id', $postIds)
                ->distinct())
            : [];

        return [$reacted, $reported, $open];
    }

    /**
     * Attaches the per-author badge numbers the thread page shows. The post count is a maintained
     * column that arrives with the eager-loaded author; the plugin count is a small aggregate over
     * the page's authors.
     *
     * @param  Collection<int, Post>  $posts
     */
    private function hydrateAuthorMetrics(Thread $thread, Collection $posts): void
    {
        $authorIds = $posts
            ->pluck('author_id')
            ->prepend($thread->author_id)
            ->filter(fn ($authorId): bool => (int) $authorId > 0)
            ->map(fn ($authorId): int => (int) $authorId)
            ->unique()
            ->values()
            ->all();

        if ($authorIds === []) {
            return;
        }

        $pluginCounts = PluginModels::class('plugin')::query()
            ->selectRaw('owner_id, COUNT(*) as aggregate')
            ->whereIn('owner_id', $authorIds)
            ->groupBy('owner_id')
            ->pluck('aggregate', 'owner_id')
            ->mapWithKeys(fn ($count, $authorId): array => [(int) $authorId => (int) $count])
            ->all();

        $apply = function (?User $author) use ($pluginCounts): void {
            $author?->setAttribute('forum_plugins_count', (int) ($pluginCounts[(int) $author->id] ?? 0));
        };

        $apply($thread->author);
        $posts->each(fn ($post) => $apply($post->author));
    }

    /**
     * Records a thread view, deduping repeated views from the same viewer within a short window so
     * refreshes do not inflate the count. Returns true if a view was counted.
     *
     * Guests are keyed on client IP and user agent, not on the session id: cookie-less crawlers get
     * a fresh session on every request, so a session-keyed dedupe never hit for them and every bot
     * request wrote to the threads table.
     */
    private function recordThreadView(Thread $thread, ?User $user, Request $request): bool
    {
        if (! $user instanceof User && CrawlerUserAgent::matches($request->userAgent())) {
            return false;
        }

        $viewerKey = $user instanceof User ? 'user:'.$user->id : 'guest:'.$this->guestFingerprint($request);

        // add() claims the dedupe window in one operation, so concurrent requests from one viewer
        // cannot both pass the check and double-increment.
        if (! Cache::add('thread_view:'.$thread->id.':'.$viewerKey, true, now()->addMinutes(self::THREAD_VIEW_DEDUPE_MINUTES))) {
            return false;
        }

        Models::class('thread')::query()->where('id', $thread->id)->increment('view_count');

        return true;
    }

    private function guestFingerprint(Request $request): string
    {
        $ip = (string) ($request->ip() ?? 'unknown-ip');
        $agent = mb_strtolower((string) $request->userAgent());

        // A keyed hash, not a bare digest: an IPv4 address plus a common user agent is cheap to
        // brute-force back out of a plain sha1, and these keys sit in a shared cache.
        return hash_hmac('sha256', $ip.'|'.$agent, (string) config('app.key'));
    }
}
