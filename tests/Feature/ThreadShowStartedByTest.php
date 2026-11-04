<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalTime;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreadShowStartedByTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_header_says_who_started_the_thread_and_when(): void
    {
        $author = User::factory()->create(['username' => 'IDEASLady']);
        $thread = $this->threadBy($author);

        $html = $this->get(route('threads.show', $thread))->assertOk()->getContent();

        $this->assertStringContainsString('Started by', $html);
        $this->assertMatchesRegularExpression(
            '#Started by\s*<a [^>]*href="'.preg_quote(route('members.show', ['user' => $author->id]), '#').'"[^>]*>IDEASLady</a>\s*on\s*'.preg_quote(LocalTime::format($thread->created_at), '#').'#',
            $html
        );
    }

    public function test_posts_leave_room_for_the_sticky_header_when_jumped_to(): void
    {
        $thread = $this->threadBy(User::factory()->create());
        $post = $thread->posts()->firstOrFail();

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('class="scroll-mt-[100px] ', false)
            ->assertSee('id="post-'.$post->id.'"', false);
    }

    private function threadBy(User $author): Thread
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general-started-by', 'is_active' => true]);
        $forum = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'Support', 'slug' => 'support-started-by', 'is_active' => true]);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread should start where it starts',
            'slug' => 'thread-should-start-started-by',
        ]);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'First post']);

        return $thread;
    }
}
