<?php

namespace App\View\Composers;

use App\Models\User;
use App\Services\ForumModerationService;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The staff moderation-queue block (what is waiting, and the reported posts), which appears on
 * every forum page for moderators who have not switched their privileges off.
 */
class ModerationQueueComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly ForumAccess $access,
        private readonly ForumModerationService $moderation,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        if (! $user instanceof User || ! $this->access->canModerate($user) || ! $this->access->actsAsStaff($user)) {
            $view->with([
                'canModerateQueue' => false,
                'pendingThreads' => collect(),
                'pendingPosts' => collect(),
                'reportedPosts' => collect(),
            ]);

            return;
        }

        [$pendingThreads, $pendingPosts] = $this->moderation->getReviewQueue();

        $pendingPosts->each(
            fn (Post $post) => $post->setAttribute('moderation_queue_show_url', $this->moderation->postShowUrl($post))
        );

        $view->with([
            'canModerateQueue' => true,
            'pendingThreads' => $pendingThreads,
            'pendingPosts' => $pendingPosts,
            'reportedPosts' => $this->moderation->getOpenReportGroups(),
        ]);
    }
}
