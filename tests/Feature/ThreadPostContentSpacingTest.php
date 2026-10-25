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
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ThreadPostContentSpacingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    // Regression test for the paragraph breaks disappearing from posted
    // content: without the "forum-rendered-content" class, Tailwind
    // Preflight zeroes <p> margins and consecutive paragraphs render flush
    // against each other with no visible gap, even though CommonMark
    // correctly emits two separate <p> tags.
    public function test_post_body_and_signature_get_the_paragraph_spacing_class(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-spacing-'.uniqid(),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Spacing Forum',
            'slug' => 'spacing-forum-'.uniqid(),
            'is_active' => true,
        ]);
        $author = User::factory()->create([
            'signature' => "Signature line one.\n\nSignature line two.",
        ]);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Spacing Thread',
            'slug' => 'spacing-thread-'.uniqid(),
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => "First paragraph.\n\nSecond paragraph.",
        ]);

        $response = $this->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSeeInOrder([
            'class="forum-rendered-content',
            'class="forum-rendered-content',
        ], false);
    }

    // Regression test for the same spacing bug persisting in the "Preview"
    // panel, which was missed by the fix above: the live-preview content div
    // renders through the identical MarkdownRenderer pipeline as the final
    // post, but its markup never carried the "forum-rendered-content" class.
    public function test_reply_composer_preview_panel_gets_the_paragraph_spacing_class(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-preview-spacing-'.uniqid(),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Preview Spacing Forum',
            'slug' => 'preview-spacing-forum-'.uniqid(),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Preview Spacing Thread',
            'slug' => 'preview-spacing-thread-'.uniqid(),
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Original post.',
        ]);

        $viewer = User::factory()->create();
        $response = $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('class="forum-rendered-content mt-2 text-sm text-[#304458]', false);
    }

    public function test_post_edit_preview_panel_gets_the_paragraph_spacing_class(): void
    {
        $this->markTestSkipped('Needs thread creation, replying or post editing: slice S3 (forum writing).');

        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-edit-preview-spacing-'.uniqid(),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Edit Preview Spacing Forum',
            'slug' => 'edit-preview-spacing-forum-'.uniqid(),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Edit Preview Spacing Thread',
            'slug' => 'edit-preview-spacing-thread-'.uniqid(),
        ]);
        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Original post.',
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->get(route('posts.edit', $post));

        $response->assertOk();
        $response->assertSee('class="forum-rendered-content mt-2 text-sm text-[#304458]"', false);
    }
}
