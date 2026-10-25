<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * The /forums index used to eager-load forums with withCount('threads'), which
 * compiles to a correlated COUNT(*) per forum and made the page examine every
 * row in `threads` on every request. forums.thread_count carries the same
 * number, maintained on write, so the correlated count must not come back.
 */
class ForumsIndexThreadCountQueryTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_forums_index_does_not_run_a_correlated_thread_count(): void
    {
        $this->seedForumsWithThreads();

        $correlated = $this->captureCorrelatedThreadCounts(
            fn () => $this->get(route('forums.index'))->assertOk()
        );

        $this->assertSame([], $correlated);
    }

    public function test_forums_index_still_reports_thread_counts(): void
    {
        [$forum] = $this->seedForumsWithThreads();

        // The maintained column is what the page reads now, so it must be the
        // number the observer wrote — not zero, and not a stale value.
        $this->get(route('forums.index'))->assertOk();

        $this->assertSame(2, (int) $forum->fresh()->thread_count);
    }

    public function test_a_newly_posted_thread_is_counted_immediately(): void
    {
        [$forum, $author] = $this->seedForumsWithThreads();

        // This is the case a TTL cache would have broken: post a thread, look
        // at /forums, and the count must already include it.
        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Brand New Thread',
            'slug' => 'brand-new-thread-'.Str::lower(Str::random(6)),
        ]);

        $this->assertSame(3, (int) $forum->fresh()->thread_count);
    }

    /**
     * @param  \Closure():mixed  $callback
     * @return list<string>
     */
    private function captureCorrelatedThreadCounts(\Closure $callback): array
    {
        $matched = [];

        DB::listen(function ($query) use (&$matched): void {
            // Quote-agnostic on purpose: the alias is `threads_count` on
            // MySQL and "threads_count" on the sqlite test connection, and an
            // assertion that only matched one of them would pass on both the
            // fixed and the regressed code.
            if (str_contains(strtolower($query->sql), 'threads_count')) {
                $matched[] = $query->sql;
            }
        });

        $callback();

        return $matched;
    }

    /**
     * @return array{0: Forum, 1: User}
     */
    private function seedForumsWithThreads(): array
    {
        $author = User::factory()->create();

        $group = ForumGroup::query()->create([
            'name' => 'Count Group',
            'slug' => 'count-group-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Count Forum',
            'slug' => 'count-forum-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        foreach (['First Thread', 'Second Thread'] as $title) {
            Thread::query()->create([
                'forum_id' => $forum->id,
                'author_id' => $author->id,
                'title' => $title,
                'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            ]);
        }

        return [$forum, $author];
    }
}
