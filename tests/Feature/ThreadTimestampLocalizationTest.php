<?php

namespace Tests\Feature;

use App\Models\User;
use App\TimeFormat;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * End-to-end proof for the fix to zen-cart.com/threads/207260: a viewer's
 * saved timezone/clock-format preference must actually change what's
 * rendered, not just be stored inertly.
 */
class ThreadTimestampLocalizationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_post_timestamp_is_shown_in_the_viewers_saved_timezone_and_format(): void
    {
        [$thread] = $this->createThreadWithPost('2026-01-15 20:30:00');
        $viewer = User::factory()->create(['timezone' => 'America/New_York', 'time_format' => TimeFormat::TWENTY_FOUR_HOUR]);

        $response = $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response->assertOk();
        // 20:30 UTC on 15 Jan 2026 is 15:30 in America/New_York (EST, UTC-5, no DST in January).
        $response->assertSee('15 Jan 2026, 15:30');
        $response->assertDontSee('20:30');
    }

    public function test_guest_sees_the_app_default_timezone_and_twelve_hour_format(): void
    {
        [$thread] = $this->createThreadWithPost('2026-01-15 20:30:00');

        $response = $this->get(route('threads.show', $thread));

        $response->assertOk();
        $this->assertSame('UTC', config('app.timezone'));
        $response->assertSee('15 Jan 2026, 8:30 PM');
    }

    /**
     * @return array{0: Thread, 1: Post}
     */
    private function createThreadWithPost(string $createdAt): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-tz-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-tz-'.Str::lower(Str::random(8)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Timestamp Localization Test Thread',
            'slug' => 'timestamp-localization-test-thread-'.Str::lower(Str::random(8)),
            'reply_count' => 0,
        ]);

        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);
        // created_at/updated_at aren't mass-assignable, so set them directly.
        $post->created_at = $createdAt;
        $post->updated_at = $createdAt;
        $post->save();

        return [$thread, $post];
    }
}
