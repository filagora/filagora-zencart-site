<?php

namespace App\Http\Controllers;

use App\Actions\BanUserAction;
use App\Http\Requests\ModeratePostRequest;
use App\Http\Requests\ModeratePostsBulkRequest;
use App\Http\Requests\ModerateThreadRequest;
use App\Models\User;
use App\Services\ContentRelocationService;
use App\Support\RelocationResult;
use Carbon\CarbonImmutable;
use Filagora\Forums\Exceptions\InvalidModeration;
use Filagora\Forums\Exceptions\ModerationNotPermitted;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Services\PostModeration;
use Filagora\Forums\Services\PostReports;
use Filagora\Forums\Services\ReadTracking;
use Filagora\Forums\Services\ThreadModeration;
use Filagora\Forums\Support\Models;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What a moderator does from the public forum pages: lock, pin, rename, approve, move or delete a
 * thread; delete, restore, approve, reject, move or split out a post; do those to several posts at
 * once; resolve a report; and undo a move. The actions and their audit log are the forums
 * package's `ThreadModeration`, `PostModeration`, `PostReports`; moving and splitting go through
 * the site's `ContentRelocationService`, which also remembers how to undo them.
 */
class ForumModerationController extends Controller
{
    public function __construct(
        private readonly ForumAccess $access,
        private readonly ThreadModeration $threads,
        private readonly PostModeration $posts,
        private readonly PostReports $reports,
        private readonly ReadTracking $readTracking,
        private readonly ContentRelocationService $relocation,
        private readonly BanUserAction $ban,
    ) {}

    public function moderateThread(ModerateThreadRequest $request, Thread $thread): RedirectResponse|JsonResponse
    {
        $moderator = $this->moderator($request);
        $action = (string) $request->validated('action');
        $reason = trim((string) $request->validated('reason', ''));
        $message = '';

        try {
            switch ($action) {
                case 'lock':
                case 'unlock':
                    $action === 'lock' ? $this->threads->lock($moderator, $thread, $reason) : $this->threads->unlock($moderator, $thread, $reason);
                    $message = $action === 'lock' ? __('forum.flash.thread_locked') : __('forum.flash.thread_unlocked');
                    break;

                case 'pin':
                case 'unpin':
                    $action === 'pin' ? $this->threads->pin($moderator, $thread, $reason) : $this->threads->unpin($moderator, $thread, $reason);
                    $message = $action === 'pin' ? __('forum.flash.thread_pinned') : __('forum.flash.thread_unpinned');
                    break;

                case 'move':
                    $destinationId = (int) $request->validated('forum_id');

                    if ((int) $thread->forum_id === $destinationId) {
                        return $this->relocationGuard($request, __('forum.errors.thread_already_in_forum'), route('threads.show', $thread));
                    }

                    $relocation = $this->relocation->moveThread($thread, Forum::query()->findOrFail($destinationId), $reason, $moderator);

                    return $request->expectsJson()
                        ? response()->json($relocation->toArray())
                        : redirect()->to($this->returnTarget($request, route('threads.show', $thread)))->with('thread_status', $relocation->summary);

                case 'rename':
                    $this->threads->rename($moderator, $thread, trim((string) $request->validated('title')));
                    $message = __('forum.flash.thread_renamed');
                    break;

                case 'approve':
                    $this->threads->approve($moderator, $thread, $reason);
                    $message = __('forum.flash.thread_approved');
                    break;

                case 'reject_permanently':
                    return $this->deleteThread($request, $thread, $moderator, $reason);

                case 'mark_unread':
                    $this->readTracking->markThreadUnread($moderator, $thread);
                    Models::class('thread_moderation_log')::query()->create([
                        'thread_id' => $thread->id,
                        'moderator_id' => $moderator->id,
                        'action' => 'mark_unread',
                        'reason' => null,
                    ]);
                    $message = __('forum.flash.thread_marked_unread');
                    break;
            }
        } catch (ModerationNotPermitted) {
            abort(403);
        } catch (InvalidModeration $invalid) {
            return $this->relocationGuard($request, $invalid->getMessage(), route('threads.show', $thread));
        }

        $returnTo = $this->internalPath((string) $request->validated('return_to', ''));

        return redirect()->to($returnTo !== '' ? $returnTo : route('threads.show', $thread))->with('thread_status', $message);
    }

    public function moderatePost(ModeratePostRequest $request, Post $post): RedirectResponse|JsonResponse
    {
        $moderator = $this->moderator($request);
        $action = (string) $request->validated('action');
        $reason = trim((string) $request->validated('reason', ''));
        $message = '';

        try {
            switch ($action) {
                case 'delete':
                    $this->posts->delete($moderator, $post, $reason);
                    $message = __('forum.flash.post_deleted');
                    break;

                case 'restore':
                    $this->posts->restore($moderator, $post);
                    $message = __('forum.flash.post_restored');
                    break;

                case 'approve':
                    $this->posts->approve($moderator, $post, $reason);
                    $message = __('forum.flash.post_approved');
                    break;

                case 'unapprove':
                    $this->posts->unapprove($moderator, $post, $reason);
                    $message = __('forum.flash.post_unapproved');
                    break;

                case 'reject':
                    $this->posts->reject($moderator, $post, $reason);
                    $message = $this->banAuthorIfRequested($request, $post->author, __('forum.flash.post_rejected'));
                    break;

                case 'reject_permanently':
                    $this->posts->rejectPermanently($moderator, $post, $reason);
                    $message = $this->banAuthorIfRequested($request, $post->author, __('forum.flash.post_rejected_permanently'));
                    break;

                case 'move':
                    $sourceThreadId = (int) $post->thread_id;
                    $destinationThreadId = (int) $request->validated('destination_thread_id');

                    if ($sourceThreadId === $destinationThreadId) {
                        return $this->relocationGuard($request, __('forum.errors.post_already_in_thread'), route('threads.show', $sourceThreadId).'#post-'.$post->id);
                    }

                    $relocation = $this->relocation->movePost($post, Thread::query()->findOrFail($destinationThreadId), $request->validated('reason'), $moderator);

                    return $this->relocationResponse($request, $relocation);

                case 'promote':
                    $destinationForum = Forum::query()->where('is_active', true)->find($request->validated('destination_forum_id'));
                    abort_unless($destinationForum instanceof Forum, 422);

                    $relocation = $this->relocation->promotePost($post, $destinationForum, trim((string) $request->validated('new_thread_title')), $request->validated('reason'), $moderator);

                    return $this->relocationResponse($request, $relocation);
            }
        } catch (ModerationNotPermitted) {
            abort(403);
        }

        $returnTo = $this->internalPath((string) $request->validated('return_to', ''));

        return redirect()
            ->to($returnTo !== '' ? $returnTo : route('threads.show', $post->thread_id).'#post-'.$post->id)
            ->with('thread_status', $message);
    }

    public function moderatePostsBulk(ModeratePostsBulkRequest $request, Thread $thread): JsonResponse|RedirectResponse
    {
        $moderator = $this->moderator($request);

        $ids = array_values(array_unique(array_map('intval', (array) $request->validated('post_ids'))));
        $posts = Models::class('post')::query()
            ->whereIn('id', $ids)
            ->where('thread_id', $thread->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($posts->count() !== count($ids)) {
            return $this->bulkFailure($request, $thread, __('forum.errors.bulk_posts_not_in_thread'));
        }

        $action = (string) $request->validated('action');
        $reason = $request->validated('reason');

        if ($action === 'delete') {
            try {
                $this->posts->deleteMany($moderator, $posts, trim((string) $reason) ?: null);
            } catch (ModerationNotPermitted) {
                abort(403);
            }

            $summary = trans_choice('forum.relocate.deleted_posts', $posts->count(), ['count' => $posts->count()]);

            return $request->expectsJson()
                ? response()->json(['message' => $summary, 'count' => $posts->count(), 'destination_url' => route('threads.show', $thread), 'undo_token' => null])
                : redirect()->to($this->returnTarget($request, route('threads.show', $thread)))->with('thread_status', $summary);
        }

        if ($action === 'move') {
            $destinationThreadId = (int) $request->validated('destination_thread_id');

            if ($destinationThreadId === (int) $thread->id) {
                return $this->bulkFailure($request, $thread, __('forum.errors.post_already_in_thread'));
            }

            $result = $this->relocation->movePostsToThread($posts, Thread::query()->findOrFail($destinationThreadId), $reason, $moderator);
        } else {
            $destinationForum = Forum::query()->where('is_active', true)->find($request->validated('destination_forum_id'));
            abort_unless($destinationForum instanceof Forum, 422);

            $result = $this->relocation->promotePostsToThread($posts, $destinationForum, trim((string) $request->validated('new_thread_title')), $reason, $moderator);
        }

        return $this->relocationResponse($request, $result);
    }

    public function undoRelocation(Request $request): JsonResponse|RedirectResponse
    {
        $moderator = $this->moderator($request);

        $result = $this->relocation->undo((string) $request->input('token', ''), $moderator);

        if ($result === null) {
            $message = __('forum.relocate.undo_expired');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 410)
                : back()->with('thread_status', $message);
        }

        return $request->expectsJson()
            ? response()->json($result->toArray())
            : back()->with('thread_status', $result->summary);
    }

    /**
     * Resolves a reported post from the front-of-site moderation queue: pick an outcome and
     * optionally ban the author. The dashboard's Resolve action goes through the same service.
     */
    public function resolvePostReport(Request $request, PostReport $postReport): RedirectResponse
    {
        $moderator = $this->moderator($request);

        $validated = $request->validate([
            'outcome' => ['required', 'in:'.implode(',', PostReports::OUTCOMES)],
            'note' => ['nullable', 'string', 'max:4000'],
            'ban' => ['sometimes', 'boolean'],
            'ban_reason' => ['nullable', 'string', 'max:4000'],
            'ban_duration' => ['nullable', 'in:1d,7d,30d,90d,1y,permanent,custom'],
            'ban_until' => ['nullable', 'date'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $post = $postReport->post;

        if ($post instanceof Post) {
            $author = $post->author;

            try {
                $this->reports->resolve($moderator, $post, $validated['outcome'], $validated['note'] ?? null);
            } catch (ModerationNotPermitted) {
                abort(403);
            }

            if ($request->boolean('ban') && $author instanceof User && ! $author->is($moderator)) {
                $until = $validated['ban_until'] ?? null;
                $this->ban->execute($author, $validated['ban_reason'] ?? null, $validated['ban_duration'] ?? 'permanent', $until !== null ? CarbonImmutable::parse($until) : null);
            }

            $message = __('forum.reports.resolved');
        } else {
            $message = __('forum.reports.post_no_longer_reportable');
        }

        return redirect()
            ->to($this->internalPath((string) ($validated['return_to'] ?? '')) ?: route('forums.index').'#forum-moderation-queue')
            ->with('thread_status', $message);
    }

    private function deleteThread(ModerateThreadRequest $request, Thread $thread, User $moderator, string $reason): RedirectResponse
    {
        $forum = $thread->forum;
        $threadPath = (string) parse_url(route('threads.show', $thread), PHP_URL_PATH);
        $author = $thread->author;
        $authorName = $author?->username ?: (string) $author?->name;

        $this->threads->delete($moderator, $thread, $reason);

        $message = __('forum.flash.thread_deleted');

        if ($request->boolean('ban') && $author instanceof User && ! $author->is($moderator)) {
            // Deleting a thread outright bans for good, whatever duration the form sent.
            $this->ban->execute($author, $request->input('ban_reason'), 'permanent', null);
            $message .= ' '.__('forum.flash.author_banned', ['name' => $authorName]);
        }

        $returnTo = $this->internalPath((string) $request->validated('return_to', ''));

        if ($returnTo === '' || str_starts_with($returnTo, $threadPath)) {
            $returnTo = $forum instanceof Forum ? route('forums.show', $forum) : route('forums.index');
        }

        return redirect()->to($returnTo)->with('thread_status', $message);
    }

    /**
     * The optional "Also ban the author" controls posted with a queue reject. A moderator can
     * never ban themselves this way. Returns the flash message, extended when a ban was applied.
     */
    private function banAuthorIfRequested(Request $request, ?User $author, string $message): string
    {
        $moderator = $request->user();

        if (! $request->boolean('ban') || ! $author instanceof User || ($moderator instanceof User && $author->is($moderator))) {
            return $message;
        }

        $this->banFromRequest($request, $author);

        return $message.' '.__('forum.flash.author_banned', ['name' => $author->username ?: $author->name]);
    }

    private function banFromRequest(Request $request, User $author): void
    {
        $until = $request->input('ban_until');

        $this->ban->execute(
            $author,
            $request->input('ban_reason'),
            (string) ($request->input('ban_duration') ?: 'permanent'),
            is_string($until) && $until !== '' ? CarbonImmutable::parse($until) : null,
        );
    }

    private function moderator(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->access->canModerate($user), 403);

        return $user;
    }

    private function relocationResponse(Request $request, RelocationResult $result): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json($result->toArray())
            : redirect()->to($this->returnTarget($request, $result->destinationUrl, $result->navigateAway))->with('thread_status', $result->summary);
    }

    private function bulkFailure(Request $request, Thread $thread, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : redirect()->to($this->returnTarget($request, route('threads.show', $thread)))->with('thread_status', $message);
    }

    /**
     * A relocate guard hit (same destination and so on): JSON so the dialog can show it, or a
     * redirect with a flash for the no-JavaScript path.
     */
    private function relocationGuard(Request $request, string $message, string $fallbackUrl): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : redirect()->to($fallbackUrl)->with('thread_status', $message);
    }

    private function returnTarget(Request $request, string $fallback, bool $ignoreReturnTo = false): string
    {
        if ($ignoreReturnTo) {
            return $fallback;
        }

        $returnTo = $this->internalPath((string) $request->input('return_to', ''));

        return $returnTo !== '' ? $returnTo : $fallback;
    }

    /**
     * Same-origin paths only: see ThreadPostingController::internalPath().
     */
    private function internalPath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === ''
            || preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1
            || preg_match('#^/[^/\\\\]#', $trimmed) !== 1) {
            return '';
        }

        return $trimmed;
    }
}
