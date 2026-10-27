<?php

namespace App\Http\Controllers;

use App\Forum\ThreadListings;
use App\Models\Contact;
use App\Models\User;
use App\Support\CacheMetrics;
use App\Support\StaffFrontendPreview;
use Filagora\Entitlements\Services\AuthorizationService;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models as ForumModels;
use Filagora\Messages\Services\IgnoredUsers;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Support\Models as PluginModels;
use Filagora\ZenCart\Legacy\OldForumProfile;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The members directory and a member's page: their plugins, releases, latest posts and threads
 * (what the viewer may see of them), the viewer's contact list, and ignoring a member. Which forums
 * and posts a viewer may see is `ForumAccess`'s answer; ignoring is the messages package's.
 */
class MembersController extends Controller
{
    public function __construct(
        private readonly ForumAccess $access,
        private readonly ThreadListings $threadListings,
        private readonly IgnoredUsers $ignored,
        private readonly AuthorizationService $authorization,
        private readonly StaffFrontendPreview $staffPreview,
        private readonly OldForumProfile $oldForum,
    ) {}

    public function index(Request $request): ViewContract
    {
        $viewer = $this->viewer($request);
        $search = trim((string) $request->query('q', ''));
        $exactMatch = $request->boolean('exact');

        $contacts = Contact::query()
            ->where('user_id', $viewer->id)
            ->with('contactUser')
            ->get()
            ->sortBy(fn (Contact $contact): string => strtolower((string) ($contact->contactUser?->username ?: $contact->contactUser?->name ?: '')))
            ->values();

        $members = User::query()
            ->where('allow_private_messages', true)
            ->when($search !== '', function (Builder $query) use ($search, $exactMatch): void {
                $query->where(function (Builder $inner) use ($search, $exactMatch): void {
                    if ($exactMatch) {
                        $needle = Str::lower($search);
                        $inner->whereRaw('LOWER(username) = ?', [$needle])->orWhereRaw('LOWER(name) = ?', [$needle]);

                        return;
                    }

                    $like = '%'.$search.'%';
                    $inner->where('username', 'like', $like)->orWhere('name', 'like', $like);
                });
            })
            ->orderByRaw('LOWER(COALESCE(username, name))')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $data = [
            'members' => $members,
            'search' => $search,
            'exactMatch' => $exactMatch,
            'contactUserIds' => $contacts->pluck('contact_user_id')->map(fn ($id): int => (int) $id)->all(),
            'viewerUserId' => (int) $viewer->id,
        ];

        return $request->boolean('members_partial')
            ? view('members.partials.all_members_results', $data)
            : view('members.index', ['contacts' => $contacts] + $data);
    }

    public function show(Request $request, User $user): ViewContract
    {
        $viewer = $this->viewer($request);
        $this->oldForum->fill($user);
        $seesAll = $this->seesNonPublic($viewer, $user);
        $other = (int) $viewer->id !== (int) $user->id;
        $pluginModel = PluginModels::class('plugin');
        $releaseModel = PluginModels::class('release');

        $plugins = $pluginModel::query()
            ->with('type')
            ->withCount('releases')
            ->addSelect([
                'latest_release_version' => $releaseModel::query()->select('version')->whereColumn('plugin_id', 'plugins.id')->latest('created_at')->latest('id')->limit(1),
                'latest_release_created_at' => $releaseModel::query()->select('created_at')->whereColumn('plugin_id', 'plugins.id')->latest('created_at')->latest('id')->limit(1),
            ])
            ->where(function (Builder $query) use ($user, $seesAll): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('releases', function (Builder $releases) use ($user, $seesAll): void {
                        $releases->where('uploaded_by_id', $user->id);

                        if (! $seesAll) {
                            $releases->where('status', PluginStatus::ACTIVE->value);
                        }
                    });
            })
            ->when(! $seesAll, fn (Builder $query) => $query->where('status', PluginStatus::ACTIVE->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'plugins_page')
            ->withQueryString();

        $releases = $releaseModel::query()
            ->with(['plugin.type', 'zcVersions'])
            ->where('uploaded_by_id', $user->id)
            ->when(! $seesAll, fn (Builder $query) => $query
                ->where('status', PluginStatus::ACTIVE->value)
                ->whereHas('plugin', fn (Builder $plugin) => $plugin->where('status', PluginStatus::ACTIVE->value)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'releases_page')
            ->withQueryString();

        return view('members.show', [
            'member' => $user,
            'plugins' => $plugins,
            'releases' => $releases,
            'posts' => $this->visiblePosts($user, $viewer, $seesAll, 10, 'posts_page'),
            'threads' => $this->visibleThreads($user, $viewer, $seesAll)->paginate(10, ['*'], 'threads_page')->withQueryString(),
            'visiblePostCount' => $this->visiblePostCount($user, $seesAll),
            'latestSessionActivity' => $this->latestActivityTimestamp($user),
            'canViewNonPublicContributions' => $seesAll,
            'viewerHasIgnoredMember' => $other && $this->ignored->isIgnoring($viewer, $user),
            'memberHasIgnoredViewer' => $other && $this->ignored->isIgnoredBy($viewer, $user),
            'viewerCanEmailMember' => $other && $this->viewerIsStaff($request, $viewer),
        ]);
    }

    public function latestPosts(Request $request, User $user): ViewContract
    {
        $viewer = $this->viewer($request);

        return view('members.posts', [
            'member' => $user,
            'posts' => $this->visiblePosts($user, $viewer, $this->seesNonPublic($viewer, $user), 20),
        ]);
    }

    public function latestThreads(Request $request, User $user): ViewContract
    {
        $viewer = $this->viewer($request);

        return view('members.threads', [
            'member' => $user,
            'threads' => $this->visibleThreads($user, $viewer, $this->seesNonPublic($viewer, $user))->paginate(20)->withQueryString(),
        ]);
    }

    public function storeContact(Request $request, User $user): RedirectResponse
    {
        $viewer = $this->viewer($request);

        if ((int) $viewer->id === (int) $user->id) {
            return $this->toIndex($request, 'You cannot add yourself as a contact.');
        }

        $contact = Contact::query()->firstOrCreate(['user_id' => (int) $viewer->id, 'contact_user_id' => (int) $user->id]);

        return $this->toIndex($request, $contact->wasRecentlyCreated ? 'Contact added.' : 'That member is already in your contacts.');
    }

    public function destroyContact(Request $request, User $user): RedirectResponse
    {
        $deleted = Contact::query()->where('user_id', (int) $this->viewer($request)->id)->where('contact_user_id', (int) $user->id)->delete();

        return $this->toIndex($request, $deleted > 0 ? 'Contact removed.' : 'Contact was already removed.');
    }

    public function storeIgnore(Request $request, User $user): RedirectResponse
    {
        $viewer = $this->viewer($request);

        if ((int) $viewer->id === (int) $user->id) {
            return $this->toProfile($request, $user, 'You cannot add yourself to your ignore list.');
        }

        return $this->toProfile($request, $user, $this->ignored->ignore($viewer, $user) ? 'Member added to ignore list.' : 'That member is already on your ignore list.');
    }

    public function destroyIgnore(Request $request, User $user): RedirectResponse
    {
        return $this->toProfile($request, $user, $this->ignored->unignore($this->viewer($request), $user) ? 'Member removed from ignore list.' : 'Member was already removed from your ignore list.');
    }

    /**
     * A member's posts the viewer may see, with a cached total: the paginator's own COUNT(*) joins
     * threads and forums and took 2.3 s cold for the busiest members (60k posts), enough to hit the
     * statement time limit in production (7 Oct 2026). The total depends on what the viewer may see,
     * so it is cached per viewer.
     */
    private function visiblePosts(User $member, User $viewer, bool $seesAll, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $postModel = ForumModels::class('post');

        $query = $postModel::query()
            ->select('posts.*')
            ->join('threads', 'threads.id', '=', 'posts.thread_id')
            ->join('forums', 'forums.id', '=', 'threads.forum_id')
            ->with(['thread:id,forum_id,title,slug,author_id', 'thread.forum:id,name,slug'])
            ->where('posts.author_id', $member->id)
            ->where('posts.is_deleted', false)
            ->where('forums.is_active', true)
            ->when(! $seesAll, fn (Builder $posts) => $posts
                ->where('posts.moderation_status', ContentModerationStatus::APPROVED->value)
                ->where('threads.moderation_status', ContentModerationStatus::APPROVED->value));

        $this->threadListings->restrictToVisibleForums($query, $viewer);

        $query->orderByDesc('posts.created_at')->orderByDesc('posts.id');

        $total = (int) CacheMetrics::remember(
            'member_posts_page_total',
            'members:posts-page-total:'.(int) $member->id.':'.(int) $viewer->id.':'.($seesAll ? 'all' : 'approved'),
            now()->addSeconds((int) config('forums.member_post_count_cache_ttl_seconds', 300)),
            fn (): int => $query->toBase()->getCountForPagination()
        );

        return $query->paginate($perPage, ['*'], $pageName, null, $total)->withQueryString();
    }

    /**
     * @return Builder<Model>
     */
    private function visibleThreads(User $member, User $viewer, bool $seesAll): Builder
    {
        $query = ForumModels::class('thread')::query()
            ->select('threads.*')
            ->join('forums', 'forums.id', '=', 'threads.forum_id')
            ->with(['forum:id,name,slug', 'author:id,username,name,email', 'lastPostAuthor:id,username,name,email'])
            ->withCount(['posts as posts_count' => function (Builder $posts) use ($seesAll): void {
                $posts->where('posts.is_deleted', false);

                if (! $seesAll) {
                    $posts->where('posts.moderation_status', ContentModerationStatus::APPROVED->value);
                }
            }])
            ->where('threads.author_id', $member->id)
            ->where('forums.is_active', true)
            ->when(! $seesAll, fn (Builder $threads) => $threads->where('threads.moderation_status', ContentModerationStatus::APPROVED->value));

        return $this->threadListings->restrictToVisibleForums($query, $viewer)
            ->orderByDesc('threads.last_post_at')
            ->orderByDesc('threads.created_at');
    }

    /**
     * The post total on a member's page. COUNT(*) is O(posts) even on the covering index built for
     * it (nearly a second for the busiest member), so it is cached. The key carries whether
     * unapproved posts are counted: staff and the member count them, nobody else does, and one
     * shared key would serve one audience the other's total.
     */
    private function visiblePostCount(User $user, bool $seesAll): int
    {
        return (int) CacheMetrics::remember(
            'member_post_count',
            'members:post-count:'.(int) $user->id.':'.($seesAll ? 'all' : 'approved'),
            now()->addSeconds((int) config('forums.member_post_count_cache_ttl_seconds', 300)),
            fn (): int => $user->posts()
                ->where('is_deleted', false)
                ->when(! $seesAll, fn (Builder $query) => $query->where('moderation_status', ContentModerationStatus::APPROVED->value))
                ->count()
        );
    }

    private function seesNonPublic(User $viewer, User $member): bool
    {
        return (int) $viewer->id === (int) $member->id || $this->authorization->allows($viewer, 'operator.forum.view_unapproved');
    }

    private function viewerIsStaff(Request $request, User $viewer): bool
    {
        return $this->authorization->allowsAny($viewer, ['operator.forum.moderate', 'operator.forum.view_unapproved', 'operator.forum.bypass_approval'])
            && $this->staffPreview->enabledFor($request, $viewer);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function toIndex(Request $request, string $status): RedirectResponse
    {
        $params = [];
        $query = trim((string) $request->input('return_q', ''));
        $page = (int) $request->input('return_page', 1);

        if ($query !== '') {
            $params['q'] = $query;
        }

        if ($request->boolean('return_exact')) {
            $params['exact'] = 1;
        }

        if ($page > 1) {
            $params['page'] = $page;
        }

        return redirect()->route('members.index', $params)->with('members_status', $status);
    }

    private function toProfile(Request $request, User $user, string $status): RedirectResponse
    {
        $tab = trim((string) $request->input('return_tab', ''));

        return redirect()->route('members.show', ['user' => $user->id] + ($tab !== '' ? ['tab' => $tab] : []))->with('members_status', $status);
    }

    /**
     * When the member was last active: their newest session, else the old forum's record of them.
     */
    private function latestActivityTimestamp(User $user): ?int
    {
        $session = DB::table('sessions')->where('user_id', $user->id)->max('last_activity');

        return is_numeric($session) ? (int) $session : $this->oldForum->lastActivity($user);
    }
}
