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

class FormSubmitLockTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_reply_form_and_page_overlay_opt_into_submit_lock(): void
    {
        [$thread] = $this->createThreadWithReply();
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('data-form-submit-lock-overlay', false);
        $response->assertSee(
            'action="'.e(route('threads.reply', $thread)).'" enctype="multipart/form-data" data-lock-on-submit',
            false
        );
    }

    public function test_new_thread_form_and_page_overlay_opt_into_submit_lock(): void
    {
        [$thread] = $this->createThreadWithReply();
        $author = User::factory()->create();

        $response = $this->actingAs($author)->get(route('threads.create.form', $thread->forum));

        $response->assertOk();
        $response->assertSee('data-form-submit-lock-overlay', false);
        $response->assertSee(
            'action="'.e(route('threads.create', $thread->forum)).'" enctype="multipart/form-data" class="grid max-w-[900px] gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit',
            false
        );
    }

    public function test_plugin_submit_form_and_page_overlay_opt_into_submit_lock(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('plugins.submit'));

        $response->assertOk();
        $response->assertSee('data-form-submit-lock-overlay', false);
        $response->assertSee(
            'action="'.e(route('plugins.store')).'" enctype="multipart/form-data" class="grid gap-5" data-lock-on-submit',
            false
        );
    }

    public function test_post_edit_form_and_page_overlay_opt_into_submit_lock(): void
    {
        [, $post] = $this->createThreadWithReply();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->get(route('posts.edit', $post));

        $response->assertOk();
        $response->assertSee('data-form-submit-lock-overlay', false);
        $response->assertSee(
            'action="'.e(route('posts.update', $post)).'" enctype="multipart/form-data" class="rounded-surface border border-[#cabca8] bg-[#ececed] p-4" data-lock-on-submit',
            false
        );
    }

    private function createThreadWithReply(array $postOverrides = []): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-lock-'.uniqid(),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Lock Support',
            'slug' => 'lock-support-'.uniqid(),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Submit Lock Thread',
            'slug' => 'submit-lock-thread-'.uniqid(),
            'reply_count' => 0,
        ]);

        $post = Post::query()->create(array_merge([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Original body',
        ], $postOverrides));

        return [$thread, $post];
    }
}
