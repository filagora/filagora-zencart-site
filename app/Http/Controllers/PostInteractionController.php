<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostReportRequest;
use App\Http\Requests\TogglePostReactionRequest;
use App\Models\User;
use Filagora\Forums\Enums\PostReportStatus;
use Filagora\Forums\Enums\ReactionRefusal;
use Filagora\Forums\Exceptions\ReactionRefused;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Services\PostReports;
use Filagora\Forums\Services\Reactions;
use Filagora\Forums\Support\Models;
use Filagora\ZenCart\Models\Post;
use Illuminate\Http\RedirectResponse;

/**
 * What a member does to someone else's post: report it, or like it. Both are checked against what
 * the member may see by the forums package; this handles the redirects and the messages.
 */
class PostInteractionController extends Controller
{
    public function __construct(
        private readonly ForumAccess $access,
        private readonly PostReports $reports,
        private readonly Reactions $reactions,
    ) {}

    public function reportPost(StorePostReportRequest $request, Post $post): RedirectResponse
    {
        $post->loadMissing('thread.forum');
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($post);
        }

        abort_unless($this->access->canViewForum($post->thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($post->thread, $user), 403);

        if ((bool) $post->is_deleted) {
            return $this->back($request, $post, __('forum.reports.post_no_longer_reportable'));
        }

        if (! $post->isApproved()) {
            return $this->back($request, $post, __('forum.reports.only_approved_posts'));
        }

        $alreadyReported = Models::class('post_report')::query()
            ->where('post_id', $post->id)
            ->where('reporter_id', $user->id)
            ->where('status', PostReportStatus::OPEN->value)
            ->exists();

        if ($alreadyReported) {
            return $this->back($request, $post, __('forum.reports.already_reported'));
        }

        $this->reports->report($user, $post, (string) $request->validated('reason'));

        return $this->back($request, $post, __('forum.reports.submitted'));
    }

    public function toggleReaction(TogglePostReactionRequest $request, Post $post): RedirectResponse
    {
        abort_unless((bool) config('features.post_likes'), 404);

        $post->loadMissing('thread.forum');
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($post);
        }

        abort_unless($this->access->canViewForum($post->thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($post->thread, $user), 403);

        try {
            $isActive = $this->reactions->toggle($user, $post, (string) $request->validated('type', 'like'));
        } catch (ReactionRefused $refusal) {
            return match ($refusal->reason) {
                ReactionRefusal::POST_DELETED => $this->back($request, $post, __('forum.reactions.deleted_post')),
                ReactionRefusal::POST_NOT_APPROVED => $this->back($request, $post, __('forum.reactions.unapproved_post')),
                ReactionRefusal::NOT_VISIBLE => abort(403),
                default => abort(422),
            };
        }

        return $this->back($request, $post, $isActive ? __('forum.reactions.added') : __('forum.reactions.removed'));
    }

    private function toLogin(Post $post): RedirectResponse
    {
        return redirect()->route('home', [
            'auth' => 'login',
            'intended' => route('threads.show', $post->thread),
        ]);
    }

    private function back(TogglePostReactionRequest|StorePostReportRequest $request, Post $post, string $message): RedirectResponse
    {
        $returnTo = $this->internalPath((string) $request->input('return_to', ''));

        return redirect()
            ->to(($returnTo !== '' ? $returnTo : route('threads.show', $post->thread)).'#post-'.$post->id)
            ->with('thread_status', $message);
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
