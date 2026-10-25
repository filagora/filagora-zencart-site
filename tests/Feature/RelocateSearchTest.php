<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ContentModerationStatus;
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

class RelocateSearchTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_thread_search_returns_rich_rows_in_match_order_not_alphabetical(): void
    {
        $forum = $this->forum('Storefront', 'Layout & Templates');
        // Apple created first (lower id), Zebra second (higher id). Engine
        // order (recency) puts Zebra first; an alphabetical re-sort would put
        // Apple first — so the two disagree and the assertion is meaningful.
        $apple = $this->thread($forum, 'Apple checkout config', 'clearing the template cache fixed it');
        $zebra = $this->thread($forum, 'Zebra checkout config', 'another config detail here');

        $data = $this->actingAs($this->moderator())
            ->getJson(route('threads.search-for-move', ['q' => 'checkout config']))
            ->assertOk()
            ->json('data');

        $this->assertSame([$zebra->id, $apple->id], array_column($data, 'id'), 'results keep engine order, not A→Z');

        $row = $data[0];
        $this->assertSame('Storefront', $row['forum']['name']);
        $this->assertSame('Layout & Templates', $row['forum']['group_name']);
        $this->assertArrayHasKey('reply_count', $row);
        $this->assertArrayHasKey('snippet_html', $row);
        $this->assertSame(route('threads.show', $zebra->id), $row['url']);
        $this->assertSame(ContentModerationStatus::APPROVED->value, $row['moderation_status']);
    }

    public function test_thread_search_excludes_the_source_thread(): void
    {
        $forum = $this->forum();
        $source = $this->thread($forum, 'Payment gateway timeout');
        $other = $this->thread($forum, 'Payment gateway retry');

        $data = $this->actingAs($this->moderator())
            ->getJson(route('threads.search-for-move', ['q' => 'Payment gateway', 'exclude_thread_id' => $source->id]))
            ->assertOk()
            ->json('data');

        $this->assertSame([$other->id], array_column($data, 'id'));
    }

    public function test_thread_search_resolves_a_pasted_id_or_link_directly(): void
    {
        $forum = $this->forum();
        $thread = $this->thread($forum, 'Totally unrelated words');
        $mod = $this->moderator();

        foreach (['#'.$thread->id, (string) $thread->id, route('threads.show', $thread->id)] as $q) {
            $data = $this->actingAs($mod)
                ->getJson(route('threads.search-for-move', ['q' => $q]))
                ->assertOk()
                ->json('data');

            $this->assertSame([$thread->id], array_column($data, 'id'), "paste form: {$q}");
        }
    }

    public function test_thread_search_with_empty_query_returns_recent_destinations(): void
    {
        $forum = $this->forum();
        $source = $this->thread($forum, 'Source thread');
        $reply = Post::query()->create(['thread_id' => $source->id, 'author_id' => $source->author_id, 'body' => 'movable', 'moderation_status' => ContentModerationStatus::APPROVED->value]);
        $dest = $this->thread($forum, 'Recent destination thread');
        $mod = $this->moderator();

        $this->assertSame([], $this->actingAs($mod)->getJson(route('threads.search-for-move', ['q' => '']))->json('data'));

        $this->actingAs($mod)->post(route('posts.moderate', $reply), [
            'action' => 'move',
            'destination_thread_id' => $dest->id,
        ])->assertRedirect();

        $data = $this->actingAs($mod)
            ->getJson(route('threads.search-for-move', ['q' => '']))
            ->assertOk()
            ->json('data');

        $this->assertSame([$dest->id], array_column($data, 'id'));
    }

    public function test_thread_search_boosts_threads_in_the_source_forum(): void
    {
        $forumA = $this->forum('Alpha');
        $forumB = $this->forum('Bravo');
        // inA is older (engine recency would rank it last); the same-forum
        // boost must still lift it above the newer inB.
        $inA = $this->thread($forumA, 'shipping label config detail');
        $inB = $this->thread($forumB, 'shipping label config note');

        $data = $this->actingAs($this->moderator())
            ->getJson(route('threads.search-for-move', ['q' => 'shipping label config', 'source_forum_id' => $forumA->id]))
            ->assertOk()
            ->json('data');

        $this->assertSame($inA->id, $data[0]['id'], 'same-forum hit floats to the top');
        $this->assertContains($inB->id, array_column($data, 'id'));
    }

    public function test_relocate_search_is_staff_only(): void
    {
        $this->forum();
        $this->actingAs(User::factory()->create())
            ->getJson(route('threads.search-for-move', ['q' => 'anything']))
            ->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->getJson(route('forums.search-for-move', ['q' => 'anything']))
            ->assertForbidden();
    }

    public function test_forum_search_returns_rows_and_resolves_a_pasted_link(): void
    {
        $forum = $this->forum('Installation & Upgrades', 'Getting Started');

        $mod = $this->moderator();
        $bySearch = $this->actingAs($mod)->getJson(route('forums.search-for-move', ['q' => 'Installation']))->assertOk()->json('data');
        $this->assertSame($forum->id, $bySearch[0]['id']);
        $this->assertSame('Getting Started', $bySearch[0]['group_name']);

        $byLink = $this->actingAs($mod)->getJson(route('forums.search-for-move', ['q' => route('forums.show', $forum)]))->assertOk()->json('data');
        $this->assertSame([$forum->id], array_column($byLink, 'id'));
    }

    private function forum(string $name = 'General Support', string $groupName = 'General'): Forum
    {
        $group = ForumGroup::query()->create([
            'name' => $groupName,
            'slug' => Str::slug($groupName).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
    }

    private int $threadCounter = 0;

    private function thread(Forum $forum, string $title, string $firstPostBody = 'First post body'): Thread
    {
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
            'moderation_status' => ContentModerationStatus::APPROVED->value,
            // Each thread is a little newer than the last, so "most recent first" is unambiguous.
            'last_post_at' => now()->addSeconds(++$this->threadCounter),
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => $firstPostBody,
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);

        return $thread;
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
