<?php

namespace App\Services;

use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\PostReportStatus;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Illuminate\Support\Collection;

class ForumModerationService
{
    /**
     * Must match ForumController::THREAD_POSTS_PER_PAGE
     * so links land on the same page the thread view itself paginates to.
     */
    private const THREAD_POSTS_PER_PAGE = 20;

    /**
     * Builds a link to a post within its thread that lands on the correct
     * pagination page, rather than always page 1 (where the "#post-{id}"
     * anchor would silently fail to scroll for any post past the first
     * page). Every caller of this method is staff working a moderation
     * queue, and the thread view paginates through *every* post for staff
     * (pending, rejected and soft-deleted included), ordered by created_at
     * - so the page is based on how many posts precede this one in that
     * unfiltered ordering.
     */
    public function postShowUrl(Post $post): string
    {
        $thread = $post->thread;

        if (! $thread instanceof Thread) {
            return '';
        }

        $precedingPosts = Models::class('post')::query()
            ->where('thread_id', $post->thread_id)
            ->where('created_at', '<', $post->created_at)
            ->count();

        $page = max(1, (int) ceil(($precedingPosts + 1) / self::THREAD_POSTS_PER_PAGE));

        $url = $page > 1
            ? route('threads.show', ['thread' => $thread, 'page' => $page])
            : route('threads.show', $thread);

        return $url.'#post-'.$post->id;
    }

    /**
     * @return array{0:Collection<int,Thread>,1:Collection<int,Post>}
     */
    public function getReviewQueue(): array
    {
        $reviewableStatuses = [
            ContentModerationStatus::PENDING->value,
            ContentModerationStatus::REJECTED->value,
        ];

        $threads = Models::class('thread')::query()
            ->whereIn('moderation_status', $reviewableStatuses)
            ->with([
                'forum:id,name',
                'author:id,username,name,email',
            ])
            ->orderByRaw("CASE WHEN moderation_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $posts = Models::class('post')::query()
            ->whereIn('moderation_status', $reviewableStatuses)
            ->with([
                'thread:id,title,forum_id,moderation_status',
                'thread.forum:id,name',
                'author:id,username,name,email',
            ])
            ->whereHas('thread')
            ->orderByRaw("CASE WHEN moderation_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return [$threads, $posts];
    }

    /**
     * Open post reports, grouped by the reported post: one card per post,
     * most-reported first, with the flag for "this is the only approved
     * reply left in its thread" so the resolver can offer to remove the
     * whole thread.
     *
     * @return Collection<int, array{post: Post, reports: Collection<int, PostReport>, count: int, is_only_post: bool, post_show_url: string}>
     */
    public function getOpenReportGroups(): Collection
    {
        return Models::class('post_report')::query()
            ->where('status', PostReportStatus::OPEN->value)
            ->whereHas('post')
            ->with([
                'reporter:id,username,name,email',
                'post:id,thread_id,author_id,body,is_deleted,moderation_status,created_at',
                'post.author:id,username,name,email,created_at,forum_posts_count',
                'post.thread:id,title,slug,forum_id',
                'post.thread.forum:id,name',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('post_id')
            ->map(function (Collection $group): ?array {
                /** @var PostReport $first */
                $first = $group->first();
                $post = $first->post;
                if (! $post instanceof Post) {
                    return null;
                }

                $approvedPosts = Models::class('post')::query()
                    ->where('thread_id', $post->thread_id)
                    ->where('is_deleted', false)
                    ->where('moderation_status', ContentModerationStatus::APPROVED->value)
                    ->count();

                return [
                    'post' => $post,
                    'reports' => $group->sortBy('created_at')->values(),
                    'count' => $group->count(),
                    'is_only_post' => $approvedPosts <= 1,
                    'post_show_url' => $this->postShowUrl($post),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $group): int => $group['count'])
            ->values();
    }
}
