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

class ThreadShowLegacyTextDecodingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_thread_page_renders_legacy_html_entities_as_the_real_character(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-legacy-text',
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Support Site Issues &amp; Questions',
            'slug' => 'support-site-issues-questions',
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome &amp; We&#039;ve moved on from vBulletin',
            'slug' => 'welcome-legacy-text',
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $response = $this->get(route('threads.show', $thread));

        // Imported vBulletin titles/names are stored with literal HTML
        // entities. Blade's {{ }} escaping then re-escapes that stored
        // markup into "&amp;amp;" in the HTML source unless it's decoded
        // first — which a browser renders as the literal text "&amp;" on
        // the page instead of "&". A single "&amp;" in the HTML source is
        // the *correct* representation of a real "&" character; the bug is
        // specifically the doubled "&amp;amp;".
        $response->assertOk();
        $response->assertSee('Support Site Issues &amp; Questions', false);
        $response->assertSee('Welcome &amp; We&#039;ve moved on from vBulletin', false);
        $response->assertDontSee('&amp;amp;', false);
        $response->assertDontSee('&amp;#039;', false);
    }

    public function test_forum_listing_relocate_control_carries_the_decoded_thread_title(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Support &amp; Feedback',
            'slug' => 'support-feedback-legacy-text',
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Source Forum',
            'slug' => 'source-forum-legacy-text',
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome &amp; We&#039;ve moved on from vBulletin',
            'slug' => 'welcome-legacy-text-move',
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->get(route('forums.show', $forum));

        $response->assertOk();
        $response->assertSee('data-thread-title="Welcome &amp; We&#039;ve moved on from vBulletin"', false);
        $response->assertDontSee('&amp;amp;', false);
        $response->assertDontSee('&amp;#039;', false);
    }
}
