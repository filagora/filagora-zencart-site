<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Support\StatsSyncScope;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserForumPostCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_post_increments_the_author_counter(): void
    {
        [$thread, $author] = $this->createThreadWithPosts(3);

        // The thread's own first post is not created by the helper, so the
        // counter reflects exactly the three replies.
        $this->assertSame(3, (int) $author->fresh()->forum_posts_count);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'One more',
        ]);

        $this->assertSame(4, (int) $author->fresh()->forum_posts_count);
    }

    public function test_deleting_a_post_decrements_the_author_counter(): void
    {
        [$thread, $author] = $this->createThreadWithPosts(3);

        Post::query()->where('thread_id', $thread->id)->first()->delete();

        $this->assertSame(2, (int) $author->fresh()->forum_posts_count);
    }

    public function test_reassigning_an_author_moves_the_count(): void
    {
        [$thread, $author] = $this->createThreadWithPosts(2);
        $newAuthor = User::factory()->create();

        $post = Post::query()->where('thread_id', $thread->id)->first();
        $post->author_id = $newAuthor->id;
        $post->save();

        $this->assertSame(1, (int) $author->fresh()->forum_posts_count);
        $this->assertSame(1, (int) $newAuthor->fresh()->forum_posts_count);
    }

    /**
     * The hot reply paths (ForumController::storeReply,
     * ForumThreadCreationService::createThread) wrap post creation in
     * withoutPostSync to skip the forum-wide recompute. That scope must not
     * take the author counter down with it, or the badge stops counting
     * exactly the posts users actually write.
     */
    public function test_the_counter_survives_the_forum_stats_suppression_scope(): void
    {
        [$thread, $author] = $this->createThreadWithPosts(0);

        StatsSyncScope::withoutPostSync(fn (): Post => Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Reply written through the suppressed path',
        ]));

        $this->assertSame(1, (int) $author->fresh()->forum_posts_count);
    }

    public function test_a_body_edit_does_not_move_the_counter(): void
    {
        [$thread, $author] = $this->createThreadWithPosts(2);

        $post = Post::query()->where('thread_id', $thread->id)->first();
        $post->body = 'Edited body';
        $post->save();

        $this->assertSame(2, (int) $author->fresh()->forum_posts_count);
    }

    public function test_the_counter_is_rendered_on_the_thread_page(): void
    {
        [$thread, $author] = $this->createThreadWithPosts(1);

        DB::table('users')->where('id', $author->id)->update(['forum_posts_count' => 4242]);

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('4,242');
    }

    public function test_the_thread_page_runs_no_author_count_aggregate(): void
    {
        [$thread] = $this->createThreadWithPosts(3);

        $matched = [];
        DB::listen(function ($query) use (&$matched): void {
            if (str_contains($query->sql, 'count(*) as aggregate') && str_contains($query->sql, 'author_id')) {
                $matched[] = $query->sql;
            }
        });

        $this->get(route('threads.show', $thread))->assertOk();

        $this->assertSame([], $matched, 'The badge must not recompute the per-author COUNT(*).');
    }

    /**
     * ImportVbulletinPostsCommand writes posts through
     * DB::table('posts')->upsert(), which fires no model events. The rebuild
     * command is the documented repair for that, and for any other drift.
     */
    public function test_the_rebuild_command_repairs_drift(): void
    {
        [$thread, $author] = $this->createThreadWithPosts(2);

        DB::table('posts')->insert([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Bulk-inserted, no model events',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(2, (int) $author->fresh()->forum_posts_count);

        $this->artisan('users:sync-post-counts')->assertSuccessful();

        $this->assertSame(3, (int) $author->fresh()->forum_posts_count);
    }

    public function test_the_rebuild_command_zeroes_an_author_with_no_posts(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['forum_posts_count' => 99]);

        $this->artisan('users:sync-post-counts', ['--user-id' => [$user->id]])->assertSuccessful();

        // GROUP BY returns no row for them; the counter still has to be
        // written or a stale non-zero badge survives forever.
        $this->assertSame(0, (int) $user->fresh()->forum_posts_count);
    }

    /**
     * @return array{0: Thread, 1: User}
     */
    private function createThreadWithPosts(int $postCount): array
    {
        $author = User::factory()->create();

        $group = ForumGroup::query()->create([
            'name' => 'Author Count Group',
            'slug' => 'author-count-group-'.uniqid(),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Author Count Forum',
            'slug' => 'author-count-forum-'.uniqid(),
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Author Count Thread',
            'slug' => 'author-count-thread-'.uniqid(),
        ]);

        for ($i = 0; $i < $postCount; $i++) {
            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $author->id,
                'body' => 'Post body '.$i,
            ]);
        }

        return [$thread, $author];
    }
}
