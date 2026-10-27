<?php

namespace App\Forum;

use App\Http\Controllers\ForumController;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Support\Models;

/**
 * The address of one post inside its thread: the thread page that holds it, anchored at the post.
 * A post's page counts the approved, undeleted posts up to it, which is what a stranger sees.
 */
class PostLinks
{
    public function urlFor(object $thread, object $post): string
    {
        $position = Models::class('post')::query()
            ->where('thread_id', $thread->getKey())
            ->where('is_deleted', false)
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->where('id', '<=', $post->getKey())
            ->count();

        return $this->pageUrl($thread, (int) ceil($position / ForumController::THREAD_POSTS_PER_PAGE)).'#post-'.$post->getKey();
    }

    public function pageUrl(object $thread, int $page): string
    {
        return $page <= 1 ? route('threads.show', $thread) : route('threads.show', ['thread' => $thread, 'page' => $page]);
    }
}
