<?php

namespace App\Services;

use App\Models\LegacyImportUserMap;
use App\Support\LegacyRedirectResult;
use Filagora\Cms\Models\CmsPage;
use Filagora\Messages\Models\PrivateConversation;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Http\Request;

class LegacyUrlResolver
{
    public function resolveShowThread(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $page = $this->normalizedPage($request);

        $legacyPostId = $this->positiveInt($request->query('p'));
        if ($legacyPostId !== null) {
            $post = Post::query()
                ->with('thread')
                ->where('legacy_post_id', $legacyPostId)
                ->first();

            if ($post?->thread instanceof Thread) {
                return LegacyRedirectResult::redirect(
                    $this->threadUrl($post->thread, $page).'#post-'.$post->id,
                    context: [
                        'matched_pattern' => 'showthread.php',
                        'lookup_key' => 'legacy_post_id',
                        'legacy_post_id' => $legacyPostId,
                    ],
                );
            }
        }

        $legacyThreadId = $this->positiveInt($request->query('t'))
            ?? $this->numericPrefixFromPath($legacyPath)
            ?? $this->numericPrefixFromRawQueryString($request);

        if ($legacyThreadId === null) {
            return LegacyRedirectResult::notFound([
                'matched_pattern' => 'showthread.php',
                'lookup_key' => 'none',
            ]);
        }

        $thread = Thread::query()
            ->where('legacy_vbulletin_thread_id', $legacyThreadId)
            ->first();

        if (! $thread instanceof Thread) {
            return LegacyRedirectResult::redirect(
                route('forums.index'),
                context: [
                    'matched_pattern' => 'showthread.php',
                    'lookup_key' => 'legacy_vbulletin_thread_id',
                    'legacy_thread_id' => $legacyThreadId,
                    'fallback' => 'forums.index',
                ],
            );
        }

        return LegacyRedirectResult::redirect(
            $this->threadUrl($thread, $page),
            context: [
                'matched_pattern' => 'showthread.php',
                'lookup_key' => 'legacy_vbulletin_thread_id',
                'legacy_thread_id' => $legacyThreadId,
            ],
        );
    }

    public function resolveShowPost(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $legacyPostId = $this->positiveInt($request->query('p'))
            ?? $this->numericPrefixFromPath($legacyPath)
            ?? $this->numericPrefixFromRawQueryString($request);

        if ($legacyPostId === null) {
            return LegacyRedirectResult::notFound([
                'matched_pattern' => 'showpost.php',
                'lookup_key' => 'none',
            ]);
        }

        $post = Post::query()
            ->with('thread')
            ->where('legacy_post_id', $legacyPostId)
            ->first();

        if ($post?->thread instanceof Thread) {
            return LegacyRedirectResult::redirect(
                $this->threadUrl($post->thread).'#post-'.$post->id,
                context: [
                    'matched_pattern' => 'showpost.php',
                    'lookup_key' => 'legacy_post_id',
                    'legacy_post_id' => $legacyPostId,
                ],
            );
        }

        return LegacyRedirectResult::redirect(
            route('forums.index'),
            context: [
                'matched_pattern' => 'showpost.php',
                'lookup_key' => 'legacy_post_id',
                'legacy_post_id' => $legacyPostId,
                'fallback' => 'forums.index',
            ],
        );
    }

    public function resolveForumDisplay(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $legacyForumId = $this->positiveInt($request->query('f'))
            ?? $this->numericPrefixFromPath($legacyPath)
            ?? $this->numericPrefixFromRawQueryString($request);

        if ($legacyForumId === null) {
            return LegacyRedirectResult::notFound([
                'matched_pattern' => 'forumdisplay.php',
                'lookup_key' => 'none',
            ]);
        }

        $forum = Forum::query()
            ->where('legacy_vbulletin_forum_id', $legacyForumId)
            ->first();

        if (! $forum instanceof Forum) {
            return LegacyRedirectResult::redirect(
                route('forums.index'),
                context: [
                    'matched_pattern' => 'forumdisplay.php',
                    'lookup_key' => 'legacy_vbulletin_forum_id',
                    'legacy_forum_id' => $legacyForumId,
                    'fallback' => 'forums.index',
                ],
            );
        }

        $parameters = ['forum' => $forum];
        $page = $this->normalizedPage($request);
        if ($page !== null) {
            $parameters['page'] = $page;
        }

        return LegacyRedirectResult::redirect(
            route('forums.show', $parameters),
            context: [
                'matched_pattern' => 'forumdisplay.php',
                'lookup_key' => 'legacy_vbulletin_forum_id',
                'legacy_forum_id' => $legacyForumId,
            ],
        );
    }

    public function resolveSearch(Request $request): LegacyRedirectResult
    {
        $mode = trim((string) $request->query('do', ''));
        $query = trim((string) $request->query('query', ''));

        if ($mode === 'getnew') {
            return LegacyRedirectResult::redirect(route('forums.unread'), context: [
                'matched_pattern' => 'search.php',
                'lookup_key' => 'do',
                'legacy_mode' => 'getnew',
            ]);
        }

        if ($mode === 'getdaily') {
            return LegacyRedirectResult::redirect(route('forums.today'), context: [
                'matched_pattern' => 'search.php',
                'lookup_key' => 'do',
                'legacy_mode' => 'getdaily',
            ]);
        }

        if ($query !== '') {
            return LegacyRedirectResult::redirect(route('search.index', ['q' => $query]), context: [
                'matched_pattern' => 'search.php',
                'lookup_key' => 'query',
            ]);
        }

        return LegacyRedirectResult::redirect(route('search.index'), context: [
            'matched_pattern' => 'search.php',
            'lookup_key' => 'none',
        ]);
    }

    public function resolveDownloads(Request $request): LegacyRedirectResult
    {
        $mode = trim((string) $request->query('do', ''));

        if ($mode === 'file') {
            $legacyPluginId = $this->positiveInt($request->query('id'));

            if ($legacyPluginId !== null) {
                $plugin = Plugin::query()
                    ->where('legacy_vbulletin_file_id', $legacyPluginId)
                    ->first();

                $lookupKey = 'legacy_vbulletin_file_id';

                if (! $plugin instanceof Plugin) {
                    $plugin = Plugin::query()->whereKey($legacyPluginId)->first();
                    $lookupKey = 'id';
                }

                if ($plugin instanceof Plugin) {
                    return LegacyRedirectResult::redirect(
                        route('plugins.show', $plugin),
                        context: [
                            'matched_pattern' => 'downloads.php',
                            'lookup_key' => $lookupKey,
                            'legacy_plugin_id' => $legacyPluginId,
                            'legacy_mode' => 'file',
                        ],
                    );
                }
            }

            return LegacyRedirectResult::redirect(route('plugins.index'), context: [
                'matched_pattern' => 'downloads.php',
                'lookup_key' => 'legacy_vbulletin_file_id',
                'legacy_plugin_id' => $legacyPluginId,
                'legacy_mode' => 'file',
                'fallback' => 'plugins.index',
            ]);
        }

        if ($mode === 'search') {
            $query = trim((string) $request->query('query', ''));

            return LegacyRedirectResult::redirect(
                $query !== '' ? route('search.index', ['q' => $query]) : route('plugins.index'),
                context: [
                    'matched_pattern' => 'downloads.php',
                    'lookup_key' => $query !== '' ? 'query' : 'none',
                    'legacy_mode' => 'search',
                ],
            );
        }

        // Note: do=versioncheck never reaches here. LegacyRedirectController
        // serves that payload inline, because the clients using it cannot follow
        // a redirect. See docs/plugin-versioncheck.md.

        return LegacyRedirectResult::redirect(route('plugins.index'), context: [
            'matched_pattern' => 'downloads.php',
            'lookup_key' => $mode !== '' ? 'do' : 'none',
            'legacy_mode' => $mode !== '' ? $mode : null,
        ]);
    }

    public function resolveContent(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $legacyContentId = $this->positiveInt($request->query('id'))
            ?? $this->numericPrefixFromPath($legacyPath)
            ?? $this->numericPrefixFromRawQueryString($request);

        if ($legacyContentId === null) {
            return LegacyRedirectResult::notFound([
                'matched_pattern' => 'content.php',
                'lookup_key' => 'none',
            ]);
        }

        $page = CmsPage::query()
            ->published()
            ->where('legacy_vbulletin_content_id', $legacyContentId)
            ->first();

        if (! $page instanceof CmsPage) {
            return LegacyRedirectResult::notFound([
                'matched_pattern' => 'content.php',
                'lookup_key' => 'legacy_vbulletin_content_id',
                'legacy_content_id' => $legacyContentId,
            ]);
        }

        return LegacyRedirectResult::redirect(
            $this->cmsPageUrl($page),
            context: [
                'matched_pattern' => 'content.php',
                'lookup_key' => 'legacy_vbulletin_content_id',
                'legacy_content_id' => $legacyContentId,
            ],
        );
    }

    public function resolveFaq(): LegacyRedirectResult
    {
        return LegacyRedirectResult::gone([
            'matched_pattern' => 'faq.php',
            'lookup_key' => 'none',
        ]);
    }

    public function resolveSendMessage(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $mode = trim((string) $request->query('do', ''));

        // ?do=mailmember&u=N was "email this member"; the profile is the
        // nearest equivalent now that the email form is gone.
        if ($mode === 'mailmember') {
            $legacyUserId = $this->positiveInt($request->query('u'))
                ?? $this->numericPrefixFromPath($legacyPath)
                ?? $this->numericPrefixFromRawQueryString($request);

            if ($legacyUserId !== null) {
                $mappedUserId = LegacyImportUserMap::query()
                    ->where('legacy_user_id', $legacyUserId)
                    ->value('user_id');

                if (is_numeric($mappedUserId) && (int) $mappedUserId > 0) {
                    return LegacyRedirectResult::redirect(
                        route('members.show', ['user' => (int) $mappedUserId]),
                        context: [
                            'matched_pattern' => 'sendmessage.php',
                            'lookup_key' => 'legacy_user_id',
                            'legacy_user_id' => $legacyUserId,
                            'legacy_mode' => $mode,
                        ],
                    );
                }
            }
        }

        // The bare form was vBulletin's Contact Us page, which has no successor.
        return LegacyRedirectResult::gone([
            'matched_pattern' => 'sendmessage.php',
            'lookup_key' => 'none',
            'legacy_mode' => $mode !== '' ? $mode : null,
        ]);
    }

    public function resolveMisc(Request $request): LegacyRedirectResult
    {
        $mode = trim((string) $request->query('do', ''));

        // ?do=whoposted&t=N is the only misc.php mode still drawing search
        // traffic; every hit carries the thread id it belongs to.
        if ($mode === 'whoposted') {
            $legacyThreadId = $this->positiveInt($request->query('t'))
                ?? $this->numericPrefixFromRawQueryString($request);

            if ($legacyThreadId !== null) {
                $thread = Thread::query()
                    ->where('legacy_vbulletin_thread_id', $legacyThreadId)
                    ->first();

                if (! $thread instanceof Thread) {
                    return LegacyRedirectResult::redirect(
                        route('forums.index'),
                        context: [
                            'matched_pattern' => 'misc.php',
                            'lookup_key' => 'legacy_vbulletin_thread_id',
                            'legacy_thread_id' => $legacyThreadId,
                            'legacy_mode' => $mode,
                            'fallback' => 'forums.index',
                        ],
                    );
                }

                return LegacyRedirectResult::redirect(
                    $this->threadUrl($thread),
                    context: [
                        'matched_pattern' => 'misc.php',
                        'lookup_key' => 'legacy_vbulletin_thread_id',
                        'legacy_thread_id' => $legacyThreadId,
                        'legacy_mode' => $mode,
                    ],
                );
            }
        }

        // showrules/showsmilies/bbcode and friends have no counterpart here.
        return LegacyRedirectResult::gone([
            'matched_pattern' => 'misc.php',
            'lookup_key' => 'none',
            'legacy_mode' => $mode !== '' ? $mode : null,
        ]);
    }

    public function resolveMember(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $legacyUserId = $this->positiveInt($request->query('u'))
            ?? $this->numericPrefixFromPath($legacyPath)
            ?? $this->numericPrefixFromRawQueryString($request);

        if ($legacyUserId !== null) {
            $mappedUserId = LegacyImportUserMap::query()
                ->where('legacy_user_id', $legacyUserId)
                ->value('user_id');

            if (is_numeric($mappedUserId) && (int) $mappedUserId > 0) {
                return LegacyRedirectResult::redirect(
                    route('members.show', ['user' => (int) $mappedUserId]),
                    context: [
                        'matched_pattern' => 'member.php',
                        'lookup_key' => 'legacy_user_id',
                        'legacy_user_id' => $legacyUserId,
                    ],
                );
            }
        }

        return LegacyRedirectResult::gone([
            'matched_pattern' => 'member.php',
            'lookup_key' => $legacyUserId !== null ? 'legacy_user_id' : 'none',
            'legacy_user_id' => $legacyUserId,
            'reason' => 'legacy_user_unmapped',
        ]);
    }

    public function resolvePrintThread(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $legacyThreadId = $this->positiveInt($request->query('t'))
            ?? $this->numericPrefixFromPath($legacyPath)
            ?? $this->numericPrefixFromRawQueryString($request);

        if ($legacyThreadId === null) {
            return LegacyRedirectResult::notFound([
                'matched_pattern' => 'printthread.php',
                'lookup_key' => 'none',
            ]);
        }

        $thread = Thread::query()
            ->where('legacy_vbulletin_thread_id', $legacyThreadId)
            ->first();

        if (! $thread instanceof Thread) {
            return LegacyRedirectResult::redirect(
                route('forums.index'),
                context: [
                    'matched_pattern' => 'printthread.php',
                    'lookup_key' => 'legacy_vbulletin_thread_id',
                    'legacy_thread_id' => $legacyThreadId,
                    'fallback' => 'forums.index',
                ],
            );
        }

        return LegacyRedirectResult::redirect(
            route('threads.show', $thread),
            context: [
                'matched_pattern' => 'printthread.php',
                'lookup_key' => 'legacy_vbulletin_thread_id',
                'legacy_thread_id' => $legacyThreadId,
            ],
        );
    }

    public function resolveNewReply(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $legacyPostId = $this->positiveInt($request->query('p'))
            ?? $this->numericPrefixFromPath($legacyPath)
            ?? $this->numericPrefixFromRawQueryString($request);

        if ($legacyPostId === null) {
            return LegacyRedirectResult::gone([
                'matched_pattern' => 'newreply.php',
                'lookup_key' => 'none',
            ]);
        }

        $post = Post::query()
            ->with('thread')
            ->where('legacy_post_id', $legacyPostId)
            ->first();

        if (! $post instanceof Post || ! $post->thread instanceof Thread) {
            return LegacyRedirectResult::redirect(
                route('forums.index'),
                context: [
                    'matched_pattern' => 'newreply.php',
                    'lookup_key' => 'legacy_post_id',
                    'legacy_post_id' => $legacyPostId,
                    'fallback' => 'forums.index',
                ],
            );
        }

        return LegacyRedirectResult::redirect(
            route('threads.show', $post->thread).'#post-'.$post->id,
            context: [
                'matched_pattern' => 'newreply.php',
                'lookup_key' => 'legacy_post_id',
                'legacy_post_id' => $legacyPostId,
            ],
        );
    }

    public function resolveExternal(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        return LegacyRedirectResult::gone([
            'matched_pattern' => 'external.php',
            'lookup_key' => 'none',
        ]);
    }

    public function resolveShowcase(): LegacyRedirectResult
    {
        return LegacyRedirectResult::gone([
            'matched_pattern' => 'showcase.php',
            'lookup_key' => 'none',
        ]);
    }

    public function resolveBlog(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        return LegacyRedirectResult::gone([
            'matched_pattern' => 'blog.php',
            'lookup_key' => 'none',
        ]);
    }

    public function resolveEntry(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        return LegacyRedirectResult::gone([
            'matched_pattern' => 'entry.php',
            'lookup_key' => 'none',
        ]);
    }

    public function resolvePrivate(Request $request, ?string $legacyPath = null): LegacyRedirectResult
    {
        $mode = trim((string) $request->query('do', ''));

        if ($mode === 'showpm') {
            $legacyPmId = $this->positiveInt($request->query('pmid'))
                ?? $this->numericPrefixFromPath($legacyPath)
                ?? $this->numericPrefixFromRawQueryString($request);

            if ($legacyPmId === null) {
                return LegacyRedirectResult::redirect(route('messages.index'), context: [
                    'matched_pattern' => 'private.php',
                    'lookup_key' => 'none',
                    'legacy_mode' => 'showpm',
                    'fallback' => 'messages.index',
                ]);
            }

            $conversation = PrivateConversation::query()
                ->where('legacy_root_pmid', $legacyPmId)
                ->first();

            if ($conversation instanceof PrivateConversation) {
                return LegacyRedirectResult::redirect(
                    route('messages.show', $conversation),
                    context: [
                        'matched_pattern' => 'private.php',
                        'lookup_key' => 'legacy_root_pmid',
                        'legacy_mode' => 'showpm',
                        'legacy_pmid' => $legacyPmId,
                    ],
                );
            }

            return LegacyRedirectResult::redirect(route('messages.index'), context: [
                'matched_pattern' => 'private.php',
                'lookup_key' => 'legacy_root_pmid',
                'legacy_mode' => 'showpm',
                'legacy_pmid' => $legacyPmId,
                'fallback' => 'messages.index',
            ]);
        }

        return LegacyRedirectResult::redirect(route('messages.index'), context: [
            'matched_pattern' => 'private.php',
            'lookup_key' => $mode !== '' ? 'do' : 'none',
            'legacy_mode' => $mode !== '' ? $mode : null,
            'fallback' => 'messages.index',
        ]);
    }

    public function resolveWiki(?string $legacyPath = null): LegacyRedirectResult
    {
        $path = is_string($legacyPath) ? trim($legacyPath) : '';

        return LegacyRedirectResult::redirect(
            rtrim((string) config('services.legacy_redirect.docs_url'), '/'),
            context: [
                'matched_pattern' => 'wiki',
                'lookup_key' => 'none',
                'legacy_path' => $path !== '' ? $path : null,
            ],
        );
    }

    private function normalizedPage(Request $request): ?int
    {
        $page = $this->positiveInt($request->query('page'));

        return $page !== null && $page > 1 ? $page : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    private function numericPrefixFromPath(?string $legacyPath): ?int
    {
        if (! is_string($legacyPath) || trim($legacyPath) === '') {
            return null;
        }

        $candidate = trim($legacyPath, '/');

        if ($candidate === '') {
            return null;
        }

        $segment = explode('/', $candidate)[0];

        if (! preg_match('/^(\d+)/', $segment, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function numericPrefixFromRawQueryString(Request $request): ?int
    {
        $queryString = trim((string) $request->server('QUERY_STRING', ''));

        if ($queryString === '') {
            return null;
        }

        foreach (explode('&', $queryString) as $part) {
            $part = trim($part);

            if ($part === '' || str_contains($part, '=')) {
                continue;
            }

            if (preg_match('/^(\d+)/', $part, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function threadUrl(Thread $thread, ?int $page = null): string
    {
        $parameters = ['thread' => $thread];

        if ($page !== null) {
            $parameters['page'] = $page;
        }

        return route('threads.show', $parameters);
    }

    private function cmsPageUrl(CmsPage $page): string
    {
        $customRoute = trim((string) ($page->custom_route ?? ''));

        if ($customRoute !== '') {
            return '/'.ltrim($customRoute, '/');
        }

        return route('cms.pages.show', ['slug' => $page->slug]);
    }
}
