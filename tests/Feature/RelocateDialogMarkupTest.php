<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffFrontendPreview;
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

class RelocateDialogMarkupTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_thread_page_renders_the_relocate_dialog_and_moderate_menu_for_staff(): void
    {
        [$thread] = $this->threadWithReply();

        $response = $this->actingAs($this->moderator())->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('data-relocate-dialog', false);
        $response->assertSee('data-relocate-open="thread-move"', false);
        $response->assertSee('data-mod-menu', false);
        $response->assertSee('data-post-menu', false);
        $response->assertSee('data-post-move-form', false);
        $response->assertSee('data-post-promote-form', false);
        $response->assertSee('data-select-posts-toggle', false);
        $response->assertSee('data-select-bar', false);
        $response->assertSee(route('threads.search-for-move'), false);
        $response->assertSee(route('forums.search-for-move'), false);
        $response->assertSee(route('moderation.relocate.undo'), false);
    }

    public function test_relocate_dialog_is_absent_for_a_regular_member(): void
    {
        [$thread] = $this->threadWithReply();

        $response = $this->actingAs(User::factory()->create())->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertDontSee('data-relocate-dialog', false);
        $response->assertDontSee('data-select-bar', false);
    }

    public function test_relocate_dialog_renders_for_a_moderator_with_frontend_preview_off(): void
    {
        [$thread] = $this->threadWithReply();

        $response = $this->actingAs($this->moderator())
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('threads.show', $thread));

        $response->assertOk();
        // The Moderate menu (Lock/Pin/Select posts) is preview-gated and hidden,
        // but the per-post Move/Promote controls still show — so the dialog they
        // drive must render too.
        $response->assertDontSee('data-select-bar', false);
        $response->assertSee('data-relocate-dialog', false);
        $response->assertSee('data-post-promote-form', false);
    }

    /**
     * @return array{0: Thread, 1: Post}
     */
    private function threadWithReply(): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Relocate dialog thread',
            'slug' => 'relocate-dialog-thread-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);

        $reply = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => User::factory()->create()->id,
            'body' => 'A reply',
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);

        return [$thread, $reply];
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
