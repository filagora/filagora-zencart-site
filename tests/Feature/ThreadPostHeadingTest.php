<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreadPostHeadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_do_not_repeat_the_thread_title_as_a_visible_heading(): void
    {
        $author = User::factory()->create(['username' => 'Kim']);
        $replier = User::factory()->create(['username' => 'Dana']);
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general-post-heading', 'is_active' => true]);
        $forum = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'Support', 'slug' => 'support-post-heading', 'is_active' => true]);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Resubmitting a Form Warning',
            'slug' => 'resubmitting-a-form-warning',
        ]);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'First post']);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $replier->id, 'body' => 'A reply', 'created_at' => now()->addMinute()]);

        $response = $this->get(route('threads.show', $thread))->assertOk();

        $response
            ->assertDontSee('Re: Resubmitting a Form Warning')
            ->assertDontSee('<h2 class="ui-card-title mb-2 text-[1.08rem]">', false)
            ->assertSee('<h2 class="sr-only">Post #1 by Kim</h2>', false)
            ->assertSee('<h2 class="sr-only">Post #2 by Dana</h2>', false);
    }
}
