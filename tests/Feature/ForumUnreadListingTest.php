<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ForumUnreadListingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private function forum(): Forum
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-unread-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Unread Test Forum',
            'slug' => 'unread-test-forum-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
    }

    private function makeThread(Forum $forum, string $title, ?string $lastPostAt, string $createdAt): Thread
    {
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => User::factory()->create()->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
        ]);

        // created_at/last_post_at are managed, so set them after insert.
        Thread::query()->whereKey($thread->getKey())->update([
            'created_at' => $createdAt,
            'last_post_at' => $lastPostAt,
        ]);

        return $thread->fresh();
    }

    private function threadActiveAt(Forum $forum, string $when, ?string $title = null): Thread
    {
        return $this->makeThread($forum, $title ?? ('Thread '.Str::random(8)), $when, $when);
    }

    private function unreadTitlesFor(User $user): array
    {
        $response = $this->actingAs($user)->get('/forums/unread');
        $response->assertOk();

        return $response->viewData('threads')
            ->getCollection()
            ->pluck('title')
            ->all();
    }

    public function test_recent_thread_is_unread_when_never_read(): void
    {
        $forum = $this->forum();
        $thread = $this->threadActiveAt($forum, now()->subDay()->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        $this->assertContains($thread->title, $this->unreadTitlesFor($user));
    }

    public function test_thread_read_after_its_last_activity_is_not_unread(): void
    {
        $forum = $this->forum();
        $thread = $this->threadActiveAt($forum, now()->subDays(2)->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        DB::table('thread_reads')->insert([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'last_read_at' => now()->subDay(),
            'is_unread_override' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotContains($thread->title, $this->unreadTitlesFor($user));
    }

    public function test_thread_with_activity_after_the_read_is_unread_again(): void
    {
        $forum = $this->forum();
        $thread = $this->threadActiveAt($forum, now()->subHours(2)->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        DB::table('thread_reads')->insert([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'last_read_at' => now()->subDays(3),
            'is_unread_override' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertContains($thread->title, $this->unreadTitlesFor($user));
    }

    public function test_forum_level_read_suppresses_its_threads(): void
    {
        $forum = $this->forum();
        $thread = $this->threadActiveAt($forum, now()->subDays(2)->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        DB::table('forum_reads')->insert([
            'user_id' => $user->id,
            'forum_id' => $forum->id,
            'last_read_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotContains($thread->title, $this->unreadTitlesFor($user));
    }

    public function test_activity_before_the_users_baseline_is_not_unread(): void
    {
        $forum = $this->forum();
        $thread = $this->threadActiveAt($forum, now()->subDays(20)->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(5)]);

        $this->assertNotContains($thread->title, $this->unreadTitlesFor($user));
    }

    public function test_override_marks_an_otherwise_read_thread_unread(): void
    {
        $forum = $this->forum();
        $thread = $this->threadActiveAt($forum, now()->subDays(2)->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        DB::table('thread_reads')->insert([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'last_read_at' => now(),
            'is_unread_override' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertContains($thread->title, $this->unreadTitlesFor($user));
    }

    public function test_override_reaches_past_the_lookback_floor(): void
    {
        config(['forums.unread_max_lookback_days' => 30]);

        $forum = $this->forum();
        $ancient = $this->threadActiveAt($forum, now()->subDays(400)->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(1000)]);

        DB::table('thread_reads')->insert([
            'user_id' => $user->id,
            'thread_id' => $ancient->id,
            'last_read_at' => now()->subDays(500),
            'is_unread_override' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertContains($ancient->title, $this->unreadTitlesFor($user));
    }

    public function test_lookback_floor_bounds_a_stale_baseline(): void
    {
        config(['forums.unread_max_lookback_days' => 30]);

        $forum = $this->forum();
        $recent = $this->threadActiveAt($forum, now()->subDays(2)->toDateTimeString());
        $ancient = $this->threadActiveAt($forum, now()->subDays(400)->toDateTimeString());

        // A 2009-era vBulletin baseline, the case the floor exists for.
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subYears(17)]);

        $titles = $this->unreadTitlesFor($user);

        $this->assertContains($recent->title, $titles);
        $this->assertNotContains($ancient->title, $titles);
    }

    public function test_null_baseline_is_also_bounded_by_the_floor(): void
    {
        config(['forums.unread_max_lookback_days' => 30]);

        $forum = $this->forum();
        $recent = $this->threadActiveAt($forum, now()->subDays(3)->toDateTimeString());
        $ancient = $this->threadActiveAt($forum, now()->subDays(400)->toDateTimeString());

        $user = User::factory()->create(['forum_read_baseline_at' => null]);

        $titles = $this->unreadTitlesFor($user);

        $this->assertContains($recent->title, $titles);
        $this->assertNotContains($ancient->title, $titles);
    }

    public function test_threads_with_no_posts_fall_back_to_created_at(): void
    {
        $forum = $this->forum();
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        $thread = $this->makeThread($forum, 'No posts yet', null, now()->subDay()->toDateTimeString());

        $this->assertContains($thread->title, $this->unreadTitlesFor($user));
    }

    /**
     * Regression test: ForumController::unread() used to call
     * ->select('threads.*') after forumListingQuery()'s addSelect() had
     * already put posts_count/posts_min_id/posts_max_id on the query,
     * silently dropping them — thread_list.blade.php then showed "0" posts
     * and sent the "last post" link to the thread's first post instead of
     * its last (the same class of bug forumListingQuery() itself was fixed
     * for — see the comment there).
     */
    public function test_unread_listing_shows_post_counts_and_links_to_the_last_post(): void
    {
        $forum = $this->forum();
        $thread = $this->threadActiveAt($forum, now()->subDay()->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        $firstPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $thread->author_id,
            'body' => 'First post',
        ]);
        $secondPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $thread->author_id,
            'body' => 'Second post',
        ]);

        $response = $this->actingAs($user)->get('/forums/unread');
        $response->assertOk();

        $listedThread = $response->viewData('threads')->getCollection()->firstWhere('id', $thread->id);
        $this->assertNotNull($listedThread, 'Expected the thread to appear in the unread listing.');
        $this->assertSame(2, (int) $listedThread->posts_count);
        $this->assertSame($firstPost->id, (int) $listedThread->posts_min_id);
        $this->assertSame($secondPost->id, (int) $listedThread->posts_max_id);

        $response->assertSee('#post-'.$secondPost->id, false);
    }

    /**
     * Guards the reason this query was rewritten, not just its results.
     *
     * COALESCE(...) in the predicate is unindexable, and an OR against the
     * left-joined thread_reads stops the optimiser restricting by date at
     * all. Together they made this a 207,472-row scan to return 30. Either
     * one creeping back would restore that without failing any behavioural
     * assertion above.
     */
    public function test_listing_query_stays_indexable(): void
    {
        $forum = $this->forum();
        $this->threadActiveAt($forum, now()->subDay()->toDateTimeString());
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            if (str_contains($query->sql, 'from "threads"') || str_contains($query->sql, 'from `threads`')) {
                $statements[] = $query->sql;
            }
        });

        $this->actingAs($user)->get('/forums/unread')->assertOk();

        $listingQueries = array_filter(
            $statements,
            fn (string $sql): bool => str_contains($sql, 'thread_reads'),
        );

        $this->assertNotEmpty($listingQueries, 'Expected the unread listing query to run.');

        foreach ($listingQueries as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('coalesce(', $sql);
            $this->assertStringContainsString('last_activity_at', $sql);
            $this->assertStringNotContainsStringIgnoringCase('is_unread_override', $sql);
        }
    }

    public function test_last_activity_at_is_generated_from_the_source_columns(): void
    {
        $forum = $this->forum();

        $withPost = $this->threadActiveAt($forum, now()->subDay()->toDateTimeString());
        $withoutPost = $this->makeThread($forum, 'Generated column no posts', null, now()->subDays(3)->toDateTimeString());

        $this->assertTrue(
            $withPost->fresh()->last_activity_at->equalTo($withPost->last_post_at),
        );
        $this->assertTrue(
            $withoutPost->fresh()->last_activity_at->equalTo($withoutPost->created_at),
        );
    }

    public function test_today_includes_a_thread_created_today_with_an_older_last_post(): void
    {
        // Posts moved or split into a new thread leave last_post_at earlier
        // than created_at. Five such threads existed on 23 Aug 2026. The
        // previous predicate (last_post_at >= X OR created_at >= X) matched
        // them; a COALESCE-based last_activity_at would not have, which is why
        // the column takes the greater of the two.
        $forum = $this->forum();
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        $thread = $this->makeThread(
            $forum,
            'Split thread created today',
            now()->subDays(2)->toDateTimeString(),
            now()->toDateTimeString(),
        );

        $response = $this->actingAs($user)->get('/forums/today');
        $response->assertOk();

        $this->assertContains(
            $thread->title,
            $response->viewData('threads')->getCollection()->pluck('title')->all(),
        );
    }

    public function test_today_excludes_a_thread_whose_activity_predates_today(): void
    {
        $forum = $this->forum();
        $user = User::factory()->create(['forum_read_baseline_at' => now()->subDays(7)]);

        $old = $this->makeThread(
            $forum,
            'Yesterday thread',
            now()->subDays(2)->toDateTimeString(),
            now()->subDays(3)->toDateTimeString(),
        );

        $response = $this->actingAs($user)->get('/forums/today');
        $response->assertOk();

        $this->assertNotContains(
            $old->title,
            $response->viewData('threads')->getCollection()->pluck('title')->all(),
        );
    }

    public function test_last_activity_at_takes_the_greater_of_the_two_timestamps(): void
    {
        $forum = $this->forum();

        $split = $this->makeThread($forum, 'Greater split', now()->subDays(5)->toDateTimeString(), now()->subDay()->toDateTimeString());
        $normal = $this->makeThread($forum, 'Greater normal', now()->subDay()->toDateTimeString(), now()->subDays(5)->toDateTimeString());

        $this->assertTrue($split->fresh()->last_activity_at->equalTo($split->created_at));
        $this->assertTrue($normal->fresh()->last_activity_at->equalTo($normal->last_post_at));
    }
}
