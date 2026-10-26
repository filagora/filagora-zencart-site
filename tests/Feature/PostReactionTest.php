<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Reaction;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PostReactionTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.post_likes' => true]);
    }

    public function test_verified_user_can_like_a_post(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.reactions.toggle', $post), [
            'type' => 'like',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
        $response->assertSessionHas('thread_status', __('forum.reactions.added'));

        $this->assertDatabaseHas('reactions', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'type' => 'like',
        ]);
    }

    public function test_liking_the_same_post_again_toggles_it_off(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.reactions.toggle', $post), [
            'type' => 'like',
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $response = $this->actingAs($user)->post(route('posts.reactions.toggle', $post), [
            'type' => 'like',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
        $response->assertSessionHas('thread_status', __('forum.reactions.removed'));
        $this->assertDatabaseCount('reactions', 0);
    }

    public function test_guest_reaction_attempt_redirects_to_login_modal_with_thread_intended_url(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();

        $this->post(route('posts.reactions.toggle', $post), [
            'type' => 'like',
        ])->assertRedirect(route('home', [
            'auth' => 'login',
            'intended' => route('threads.show', $thread, false),
        ]));

        $this->assertDatabaseCount('reactions', 0);
    }

    public function test_unverified_user_cannot_react_to_post(): void
    {
        [, $post] = $this->createPublicThreadWithPost();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('posts.reactions.toggle', $post), [
                'type' => 'like',
            ])
            ->assertRedirect(route('verification.notice', [
                'intended' => '/posts/'.$post->id.'/reactions/toggle',
            ]));

        $this->assertDatabaseCount('reactions', 0);
    }

    public function test_thread_page_shows_like_count_and_liked_state_for_current_user(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('posts.reactions.toggle', $post), ['type' => 'like']);
        $this->actingAs($other)->post(route('posts.reactions.toggle', $post), ['type' => 'like']);

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Liked (2)', false);
    }

    /**
     * @return array{0: Thread, 1: Post}
     */
    public function test_likes_default_to_disabled_in_the_feature_config(): void
    {
        $this->assertStringContainsString(
            "'post_likes' => env('FEATURE_POST_LIKES', false)",
            (string) file_get_contents(base_path('config/features.php'))
        );
    }

    public function test_toggle_endpoint_is_unavailable_when_likes_are_disabled(): void
    {
        config(['features.post_likes' => false]);
        [, $post] = $this->createPublicThreadWithPost();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.reactions.toggle', $post), ['type' => 'like'])
            ->assertNotFound();

        $this->assertDatabaseCount('reactions', 0);
    }

    public function test_thread_page_hides_like_controls_when_likes_are_disabled(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $user = User::factory()->create();
        Reaction::query()->create(['post_id' => $post->id, 'user_id' => $user->id, 'type' => 'like']);

        config(['features.post_likes' => false]);

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Liked (', false)
            ->assertDontSee('Like (', false)
            ->assertDontSee('Likes:', false)
            ->assertDontSee('posts/'.$post->id.'/reactions/toggle', false);

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Like (', false);
    }

    private function createPublicThreadWithPost(): array
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Announcements',
            'slug' => 'announcements-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Reaction Test Thread',
            'slug' => 'reaction-test-thread-'.Str::lower(Str::random(6)),
        ]);
        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Original post body.',
        ]);

        $thread->forceFill([
            'last_post_author_id' => $author->id,
            'last_post_at' => $post->created_at,
        ])->save();

        return [$thread, $post];
    }
}
