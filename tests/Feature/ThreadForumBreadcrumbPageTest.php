<?php

namespace Tests\Feature;

use App\Forum\ThreadListings;
use App\Models\User;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreadForumBreadcrumbPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_breadcrumb_links_to_the_forum_page_that_holds_the_thread(): void
    {
        [$forum, $threads] = $this->forumWithThreads(65);

        $this->assertForumLink($threads[0], $forum, null);
        $this->assertForumLink($threads[29], $forum, null);
        $this->assertForumLink($threads[30], $forum, 2);
        $this->assertForumLink($threads[64], $forum, 3);
    }

    public function test_pinned_threads_push_the_rest_down_a_page(): void
    {
        [$forum, $threads] = $this->forumWithThreads(31);
        Thread::query()->whereKey($threads[30]->id)->update(['is_pinned' => true]);

        $this->assertForumLink($threads[30], $forum, null);
        $this->assertForumLink($threads[29], $forum, 2);
    }

    public function test_the_page_agrees_with_the_paginated_listing(): void
    {
        [$forum, $threads] = $this->forumWithThreads(40);
        Thread::query()->whereKey($threads[35]->id)->update(['is_pinned' => true]);
        Thread::query()->whereKey($threads[5]->id)->update(['last_post_at' => null]);

        $listings = app(ThreadListings::class);

        foreach ($threads as $thread) {
            $thread = $thread->fresh();
            $thread->setRelation('forum', $forum);
            $page = $listings->forumPageOf($thread, null);
            $ids = $listings->inForum($forum->id, null, false)->paginate(30, ['*'], 'page', $page)->pluck('id');

            $this->assertTrue($ids->contains($thread->id), "Thread {$thread->id} is not on page {$page}.");
        }
    }

    public function test_threads_with_identical_timestamps_get_distinct_positions(): void
    {
        [$forum, $threads] = $this->forumWithThreads(35);
        $stamp = now()->subDay();
        Thread::query()->where('forum_id', $forum->id)->update(['last_post_at' => $stamp, 'created_at' => $stamp]);

        $listings = app(ThreadListings::class);
        $pages = [];

        foreach ($threads as $thread) {
            $thread = $thread->fresh();
            $thread->setRelation('forum', $forum);
            $pages[] = $listings->forumPageOf($thread, null);
        }

        $this->assertCount(30, array_keys($pages, 1, true));
        $this->assertCount(5, array_keys($pages, 2, true));

        $pageTwo = $listings->inForum($forum->id, null, false)->paginate(30, ['*'], 'page', 2)->pluck('id')->all();
        foreach ($threads as $i => $thread) {
            $this->assertSame(in_array($thread->id, $pageTwo, true), $pages[$i] === 2);
        }
    }

    public function test_threads_the_viewer_cannot_see_do_not_count_towards_the_page(): void
    {
        [$forum, $threads] = $this->forumWithThreads(31);
        Thread::query()->whereKey($threads[0]->id)->update(['moderation_status' => ContentModerationStatus::PENDING]);

        // With the newest thread hidden from guests, the 31st thread moves up onto page 1.
        $this->assertForumLink($threads[30], $forum, null);
    }

    public function test_a_thread_outside_the_listing_links_to_the_first_page(): void
    {
        [$forum, $threads] = $this->forumWithThreads(31);
        Thread::query()->whereKey($threads[30]->id)->update(['moderation_status' => ContentModerationStatus::PENDING]);

        $hidden = $threads[30]->fresh();
        $hidden->setRelation('forum', $forum);

        $this->assertSame(1, app(ThreadListings::class)->forumPageOf($hidden, null));
    }

    public function test_post_edit_and_thread_search_breadcrumbs_use_the_same_link(): void
    {
        [$forum, $threads] = $this->forumWithThreads(31);
        $thread = $threads[30];
        $author = User::query()->findOrFail($thread->author_id);
        $post = $thread->posts()->firstOrFail();

        $expected = 'href="'.route('forums.show', ['forum' => $forum, 'page' => 2]).'"';

        $this->actingAs($author)->get(route('posts.edit', $post))->assertOk()->assertSee($expected, false);
        $this->get(route('threads.search', $thread))->assertOk()->assertSee($expected, false);
    }

    public function test_breadcrumbs_include_the_forum_group_and_link_every_step(): void
    {
        [$forum, $threads] = $this->forumWithThreads(1);
        $thread = $threads[0];
        $anchor = 'href="'.route('forums.index').'#forum-group-'.$forum->forum_group_id.'"';

        $this->get(route('threads.show', $thread))->assertOk()
            ->assertSeeInOrder([route('forums.index'), $anchor, route('forums.show', $forum), 'Thread 0'], false)
            ->assertSee($anchor, false);

        $this->get(route('forums.show', $forum))->assertOk()->assertSee($anchor, false);
        $this->get(route('threads.search', $thread))->assertOk()->assertSee($anchor, false)
            ->assertSee('href="'.route('threads.show', $thread).'"', false);
        $this->get(route('forums.index'))->assertOk()
            ->assertSee('id="forum-group-'.$forum->forum_group_id.'"', false);
    }

    private function assertForumLink(Thread $thread, Forum $forum, ?int $page): void
    {
        $href = route('forums.show', array_filter(['forum' => $forum, 'page' => $page]));

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('href="'.$href.'"', false);
    }

    /**
     * Newest first: index 0 sorts first in the forum listing.
     *
     * @return array{0: Forum, 1: list<Thread>}
     */
    private function forumWithThreads(int $count): array
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general-breadcrumb', 'is_active' => true]);
        $forum = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'Support', 'slug' => 'support-breadcrumb', 'is_active' => true]);
        $author = User::factory()->create();

        $threads = [];
        for ($i = 0; $i < $count; $i++) {
            $thread = Thread::query()->create([
                'forum_id' => $forum->id,
                'author_id' => $author->id,
                'title' => 'Thread '.$i,
                'slug' => 'thread-breadcrumb-'.$i,
                'last_post_at' => now()->subHours($i),
                'created_at' => now()->subHours($i + 1),
            ]);
            Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'Body '.$i]);
            $threads[] = $thread;
        }

        return [$forum, $threads];
    }
}
