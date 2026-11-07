<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalTime;
use Filagora\Forums\Enums\ForumVisibility;
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
            ->assertSee('class="scroll-mt-[calc(var(--site-header-h,100px)+12px)] ', false)
            ->assertSee('id="post-'.$post->id.'"', false);
    }

    public function test_thread_preview_returns_first_and_latest_post_excerpts(): void
    {
        $author = User::factory()->create();
        $thread = $this->threadBy($author);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'Latest reply text', 'created_at' => now()->addMinute()]);

        $this->getJson(route('threads.preview', $thread))
            ->assertOk()
            ->assertJsonPath('first.excerpt', 'First post')
            ->assertJsonPath('latest.excerpt', 'Latest reply text')
            ->assertHeader('Cache-Control', 'max-age=60, private');
    }

    public function test_thread_preview_omits_latest_for_a_single_post_thread(): void
    {
        $thread = $this->threadBy(User::factory()->create());

        $this->getJson(route('threads.preview', $thread))
            ->assertOk()
            ->assertJsonPath('latest', null);
    }

    public function test_thread_preview_hides_deleted_posts(): void
    {
        $author = User::factory()->create();
        $thread = $this->threadBy($author);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'Secret deleted', 'is_deleted' => true, 'created_at' => now()->addMinute()]);

        $this->getJson(route('threads.preview', $thread))
            ->assertOk()
            ->assertJsonPath('latest', null)
            ->assertDontSee('Secret deleted');
    }

    public function test_thread_preview_shows_nothing_from_a_forum_the_viewer_could_not_open(): void
    {
        $thread = $this->threadBy(User::factory()->create());
        $thread->forum->forceFill(['visibility' => ForumVisibility::STAFF_ONLY])->save();

        $this->getJson(route('threads.preview', $thread))->assertForbidden();
    }

    public function test_thread_preview_strips_markup_and_clips_long_posts(): void
    {
        $author = User::factory()->create();
        $thread = $this->threadBy($author);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => '**Bold** '.str_repeat('word ', 100), 'created_at' => now()->addMinute()]);

        $latest = $this->getJson(route('threads.preview', $thread))->assertOk()->json('latest.excerpt');

        $this->assertStringStartsWith('Bold word', $latest);
        $this->assertLessThanOrEqual(223, mb_strlen($latest));
    }

    public function test_thread_list_titles_carry_the_preview_url(): void
    {
        $thread = $this->threadBy(User::factory()->create());

        $this->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertSee('data-thread-preview-url="'.route('threads.preview', $thread).'"', false);
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
