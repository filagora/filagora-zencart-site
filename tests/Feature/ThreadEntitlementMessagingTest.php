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

class ThreadEntitlementMessagingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_thread_page_uses_entitlement_language_for_reply_gating_message(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'reply_entitlement_scope' => 'forums.general_support.reply',
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $viewer = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Support thread',
            'slug' => 'support-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
            'created_at' => now(),
            'last_post_at' => now(),
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $response = $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee("Replying to other users' threads in this forum requires the", false);
        $response->assertSee('forums.general_support.reply');
        $response->assertSee('entitlement.', false);
        $response->assertDontSee('membership entitlement.', false);
    }
}
