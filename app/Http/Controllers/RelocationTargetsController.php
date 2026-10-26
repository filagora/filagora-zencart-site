<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ContentRelocationService;
use App\Support\LegacyTextDecoder;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Search\ForumSearch;
use Filagora\Forums\Search\ForumSearchCriteria;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The pickers in the move and split dialogs: which thread or forum to put content into. Moderators
 * only. The box takes a pasted link or "#id", a search, or (when empty) the moderator's recent
 * targets.
 */
class RelocationTargetsController extends Controller
{
    public function __construct(
        private readonly ForumAccess $access,
        private readonly ForumSearch $search,
        private readonly ContentRelocationService $relocation,
    ) {}

    public function threads(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->access->canModerate($user)) {
            return response()->json(['data' => []], 403);
        }

        $threadModel = Models::class('thread');
        $query = trim((string) $request->query('q', ''));

        $excludePostId = (int) $request->query('exclude_post_id', 0);
        $excludeThreadId = (int) $request->query('exclude_thread_id', 0);

        if ($excludePostId > 0) {
            $excludeThreadId = (int) (Models::class('post')::query()->whereKey($excludePostId)->value('thread_id') ?? $excludeThreadId);
        }

        $sourceForumId = (int) $request->query('source_forum_id', 0);

        // Paste a thread link or #id to jump straight to it.
        if ($directId = $this->directId($query, 'thread')) {
            $thread = $threadModel::query()->with('forum.group')->find($directId);

            return response()->json(['data' => $thread !== null && (int) $thread->id !== $excludeThreadId
                ? [$this->threadRow($thread, $query)]
                : []]);
        }

        if ($query === '') {
            $recent = $this->relocation->recentThreadIds($user);

            $threads = $threadModel::query()->with('forum.group')
                ->whereIn('id', $recent)
                ->where('id', '!=', $excludeThreadId)
                ->get()
                ->sortBy(fn ($thread) => array_search((int) $thread->id, $recent, true))
                ->values();

            return response()->json(['data' => $threads->map(fn ($thread): array => $this->threadRow($thread))->all()]);
        }

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $rows = collect($this->search->threads(new ForumSearchCriteria(query: $query), $user)->items())
            ->reject(fn ($thread): bool => (int) $thread->id === $excludeThreadId)
            ->map(fn ($thread): array => $this->threadRow($thread->loadMissing('forum.group'), $query))
            ->values();

        if ($sourceForumId > 0) {
            $rows = $rows->sortBy(fn (array $row) => $row['forum']['id'] === $sourceForumId ? 0 : 1)->values();
        }

        return response()->json(['data' => $rows->all()]);
    }

    public function forums(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->access->canModerate($user)) {
            return response()->json(['data' => []], 403);
        }

        $forumModel = Models::class('forum');
        $query = trim((string) $request->query('q', ''));
        $excludeForumId = (int) $request->query('exclude_forum_id', 0);
        $sourceGroupId = (int) $request->query('source_group_id', 0);

        if ($directId = $this->directId($query, 'forum')) {
            $forum = $forumModel::query()->with('group')->where('is_active', true)->find($directId);

            return response()->json(['data' => $forum !== null && (int) $forum->id !== $excludeForumId
                ? [$this->forumRow($forum)]
                : []]);
        }

        if ($query === '') {
            $recent = $this->relocation->recentForumIds($user);

            $forums = $forumModel::query()->with('group')->where('is_active', true)
                ->whereIn('id', $recent)
                ->where('id', '!=', $excludeForumId)
                ->get()
                ->sortBy(fn ($forum) => array_search((int) $forum->id, $recent, true))
                ->values();

            return response()->json(['data' => $forums->map(fn ($forum): array => $this->forumRow($forum))->all()]);
        }

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        // The forum list is tiny (low hundreds) and its names carry legacy HTML entities, so match
        // in PHP against the decoded name and group rather than asking a search engine.
        $needle = mb_strtolower($query);

        $rows = $forumModel::query()->with('group')
            ->where('is_active', true)
            ->where('id', '!=', $excludeForumId)
            ->orderByRaw('LOWER(name)')
            ->get()
            ->map(fn ($forum): array => $this->forumRow($forum))
            ->filter(fn (array $row): bool => str_contains(mb_strtolower($row['title']), $needle)
                || str_contains(mb_strtolower($row['group_name']), $needle))
            ->sortBy(fn (array $row): int => match (true) {
                str_starts_with(mb_strtolower($row['title']), $needle) => 0,
                str_contains(mb_strtolower($row['title']), $needle) => 1,
                default => 2,
            })
            ->values();

        if ($sourceGroupId > 0) {
            $rows = $rows->sortBy(fn (array $row) => $row['group_id'] === $sourceGroupId ? 0 : 1)->values();
        }

        return response()->json(['data' => $rows->take(40)->all()]);
    }

    /**
     * Resolves a pasted link or "#id" to an id, but only when it matches the kind of thing being
     * searched: a /forums/ link in the thread box, or the reverse, must not resolve.
     *
     * @param  'thread'|'forum'  $kind
     */
    private function directId(string $query, string $kind): int
    {
        $segment = $kind === 'forum' ? 'forums' : 'threads';

        if (preg_match('#/'.$segment.'/(\d+)#', $query, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^#?(\d{1,12})$/', $query, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function threadRow(object $thread, string $query = ''): array
    {
        return [
            'id' => (int) $thread->id,
            'title' => LegacyTextDecoder::decode((string) $thread->title),
            'url' => route('threads.show', $thread->id),
            'forum' => [
                'id' => (int) $thread->forum_id,
                'name' => LegacyTextDecoder::decode((string) ($thread->forum?->name ?? '')),
                'group_name' => LegacyTextDecoder::decode((string) ($thread->forum?->group?->name ?? '')),
            ],
            'reply_count' => (int) $thread->reply_count,
            'last_activity_human' => $thread->last_post_at?->diffForHumans(),
            'is_locked' => (bool) $thread->is_locked,
            'is_pinned' => (bool) $thread->is_pinned,
            'moderation_status' => $thread->moderation_status?->value,
            'snippet_html' => $this->snippet($thread, $query),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forumRow(object $forum): array
    {
        return [
            'id' => (int) $forum->id,
            'title' => LegacyTextDecoder::decode((string) $forum->name),
            'url' => route('forums.show', $forum),
            'group_id' => (int) ($forum->forum_group_id ?? 0),
            'group_name' => LegacyTextDecoder::decode((string) ($forum->group?->name ?? '')),
        ];
    }

    /**
     * A short excerpt of the opening post, with the search term marked. Only `<mark>` survives;
     * everything else is escaped.
     */
    private function snippet(object $thread, string $query): ?string
    {
        $body = (string) Models::class('post')::query()
            ->where('thread_id', $thread->id)
            ->where('is_deleted', false)
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->orderBy('id')
            ->value('body');
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        if ($plain === '') {
            return null;
        }

        $excerpt = e(mb_substr($plain, 0, 160));
        $term = trim($query);

        if ($term !== '' && mb_strlen($term) >= 2) {
            $excerpt = preg_replace('/('.preg_quote(e($term), '/').')/i', '<mark>$1</mark>', $excerpt) ?? $excerpt;
        }

        return $excerpt;
    }
}
