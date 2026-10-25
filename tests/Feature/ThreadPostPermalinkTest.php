<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ThreadPostPermalinkTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_first_page_post_number_links_to_its_anchor_without_a_page_param(): void
    {
        $thread = $this->createThreadWithPosts(1);
        $post = $thread->posts()->firstOrFail();

        $response = $this->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee(
            'href="'.e(route('threads.show', $thread).'#post-'.$post->id).'"',
            false
        );
    }

    public function test_second_page_post_number_links_include_the_page_param(): void
    {
        $thread = $this->createThreadWithPosts(21);
        $lastPost = $thread->posts()->orderByDesc('id')->firstOrFail();

        $response = $this->get(route('threads.show', ['thread' => $thread, 'page' => 2]));

        $response->assertOk();
        $response->assertSee(
            'href="'.e(route('threads.show', ['thread' => $thread, 'page' => 2]).'#post-'.$lastPost->id).'"',
            false
        );
    }

    private function createThreadWithPosts(int $postCount): Thread
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-permalink-'.uniqid(),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Permalink Forum',
            'slug' => 'permalink-forum-'.uniqid(),
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Permalink Thread',
            'slug' => 'permalink-thread-'.uniqid(),
        ]);

        for ($i = 0; $i < $postCount; $i++) {
            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $author->id,
                'body' => 'Post body '.$i,
            ]);
        }

        return $thread;
    }
}
