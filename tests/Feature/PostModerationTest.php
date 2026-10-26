<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ForumModerationService;
use App\Support\StaffFrontendPreview;
use Carbon\Carbon;
use Filagora\Core\Models\Setting;
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

class PostModerationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_user_can_delete_and_restore_post(): void
    {
        [$thread, $post] = $this->createThreadWithReply();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $deleteResponse = $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'delete',
            'reason' => 'Spam content',
            'return_to' => route('threads.show', $thread),
        ]);

        $deleteResponse->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
        $deleteResponse->assertSessionHas('thread_status', __('forum.flash.post_deleted'));
        $this->assertTrue((bool) $post->fresh()->is_deleted);
        $this->assertSame('Spam content', (string) $post->fresh()->deleted_reason);
        $this->assertDatabaseHas('post_moderation_logs', [
            'thread_id' => $thread->id,
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'action' => 'delete',
            'reason' => 'Spam content',
        ]);

        $restoreResponse = $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'restore',
            'return_to' => route('threads.show', $thread),
        ]);

        $restoreResponse->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
        $restoreResponse->assertSessionHas('thread_status', __('forum.flash.post_restored'));
        $restored = $post->fresh();
        $this->assertFalse((bool) $restored->is_deleted);
        $this->assertNull($restored->deleted_reason);
        $this->assertDatabaseHas('post_moderation_logs', [
            'thread_id' => $thread->id,
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'action' => 'restore',
            'reason' => null,
        ]);
    }

    public function test_deleting_the_latest_post_recomputes_thread_last_post_at_instead_of_nulling_it(): void
    {
        // No explicit created_at override here: the reply must keep its own
        // natural insert-time timestamp, which createThreadWithReply()
        // guarantees lands after the thread's first post since it's created
        // afterward. Forcing an earlier caller-supplied `now()` (captured
        // before the first post even exists) can invert that ordering and
        // masks the real assertion this test exists to catch.
        [$thread, $reply] = $this->createThreadWithReply();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)->post(route('posts.moderate', $reply), [
            'action' => 'delete',
            'reason' => 'Cleanup',
            'return_to' => route('threads.show', $thread),
        ]);

        $firstPost = Post::query()
            ->where('thread_id', $thread->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        $thread->refresh();
        $this->assertNotNull($thread->last_post_at);
        $this->assertTrue($thread->last_post_at->equalTo($firstPost->created_at));
    }

    public function test_non_staff_user_cannot_moderate_post(): void
    {
        [, $post] = $this->createThreadWithReply();
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)->post(route('posts.moderate', $post), [
            'action' => 'delete',
            'reason' => 'Attempted moderation',
        ]);

        $response->assertForbidden();
        $this->assertFalse((bool) $post->fresh()->is_deleted);
    }

    public function test_deleted_posts_are_hidden_from_regular_users_but_visible_to_staff(): void
    {
        [$thread, $post] = $this->createThreadWithReply([
            'body' => 'Sensitive post text',
            'is_deleted' => true,
            'deleted_reason' => 'Rule violation',
            'deleted_at' => now(),
        ]);

        $regularUser = User::factory()->create();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $regularResponse = $this->actingAs($regularUser)->get(route('threads.show', $thread));
        $regularResponse->assertOk();
        $regularResponse->assertDontSee('Sensitive post text');
        $regularResponse->assertDontSee('This post was deleted by staff.');

        $staffResponse = $this->actingAs($moderator)->get(route('threads.show', $thread));
        $staffResponse->assertOk();
        $staffResponse->assertSee('This post was deleted by staff.');
        $staffResponse->assertSee('Rule violation');
        $staffResponse->assertSee('Restore');
    }

    public function test_pending_and_rejected_posts_are_hidden_from_regular_users_but_visible_to_staff(): void
    {
        [$thread, $pendingPost] = $this->createThreadWithReply([
            'body' => 'Awaiting review post text',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $regularUser = User::factory()->create();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $regularResponse = $this->actingAs($regularUser)->get(route('threads.show', $thread));
        $regularResponse->assertOk();
        $regularResponse->assertDontSee('Awaiting review post text');

        $staffResponse = $this->actingAs($moderator)->get(route('threads.show', $thread));
        $staffResponse->assertOk();
        $staffResponse->assertSee('Awaiting review post text');
        $staffResponse->assertSee('id="post-'.$pendingPost->id.'"', false);
    }

    public function test_post_delete_requires_reason_and_shows_validation_error(): void
    {
        [$thread, $post] = $this->createThreadWithReply();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)
            ->followingRedirects()
            ->from(route('threads.show', $thread))
            ->post(route('posts.moderate', $post), [
                'action' => 'delete',
                'reason' => '',
            ]);

        $response->assertOk();
        $response->assertSee('The reason field is required when action is delete.');
        $this->assertFalse((bool) $post->fresh()->is_deleted);
    }

    public function test_post_moderation_history_panel_is_staff_only(): void
    {
        [$thread, $post] = $this->createThreadWithReply();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');
        $regularUser = User::factory()->create();

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'delete',
            'reason' => 'Off-topic',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $staffResponse = $this->actingAs($moderator)->get(route('threads.show', $thread));
        $staffResponse->assertOk();
        $staffResponse->assertSee('Post Moderation History');
        $staffResponse->assertSee('Off-topic');

        $regularResponse = $this->actingAs($regularUser)->get(route('threads.show', $thread));
        $regularResponse->assertOk();
        $regularResponse->assertDontSee('Post Moderation History');
        $regularResponse->assertDontSee('Off-topic');
    }

    public function test_staff_user_can_edit_post_from_frontend_forum(): void
    {
        [$thread, $post] = $this->createThreadWithReply([
            'body' => 'Original reply text',
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get(route('posts.edit', ['post' => $post, 'return_to' => route('threads.show', $thread)]))
            ->assertOk()
            ->assertSee('Edit Post')
            ->assertSee('Original reply text');

        $this->actingAs($moderator)
            ->put(route('posts.update', $post), [
                'body' => 'Edited by staff',
                'return_to' => route('threads.show', $thread),
            ])
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id)
            ->assertSessionHas('thread_status', __('forum.flash.post_updated'));

        $post->refresh();
        $this->assertSame('Edited by staff', $post->body);
        $this->assertSame('markdown', $post->content_format);

        $this->actingAs($moderator)
            ->get(route('threads.show', $thread))
            ->assertSee('Edited by staff');
    }

    public function test_regular_user_can_edit_own_recent_post_within_configured_window(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'forum_post_edit_window_minutes'],
            ['value' => '30', 'value_type' => 'integer', 'is_public' => false]
        );
        Setting::query()->updateOrCreate(
            ['key' => 'forum_post_edit_grace_period_minutes'],
            ['value' => '5', 'value_type' => 'integer', 'is_public' => false]
        );

        [$thread, $post] = $this->createThreadWithReply([
            'body' => 'Author reply',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        $regularUser = $post->author;

        $this->actingAs($regularUser)
            ->get(route('posts.edit', $post))
            ->assertOk()
            ->assertSee('Edit Post');

        $this->actingAs($regularUser)
            ->put(route('posts.update', $post), [
                'body' => 'Author updated reply',
                'return_to' => route('threads.show', $thread),
            ])
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $post->refresh();

        $this->assertSame('Author updated reply', $post->body);
        $this->assertSame('markdown', $post->content_format);
        $this->assertSame($regularUser->id, $post->edited_by_id);
        $this->assertNotNull($post->edited_at);

        $this->actingAs($regularUser)
            ->get(route('threads.show', $thread))
            ->assertSee('[edited]', false)
            ->assertSee('Author updated reply');
    }

    public function test_regular_user_cannot_edit_someone_elses_post_or_old_own_post(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'forum_post_edit_window_minutes'],
            ['value' => '30', 'value_type' => 'integer', 'is_public' => false]
        );

        [, $post] = $this->createThreadWithReply([
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ]);

        $this->actingAs($post->author)
            ->get(route('posts.edit', $post))
            ->assertForbidden();

        $this->actingAs($post->author)
            ->put(route('posts.update', $post), [
                'body' => 'Attempted late edit',
            ])
            ->assertForbidden();

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('posts.edit', $post))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('posts.update', $post), [
                'body' => 'Attempted edit',
            ])
            ->assertForbidden();
    }

    public function test_regular_user_cannot_edit_own_post_after_losing_paid_forum_access(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'forum_post_edit_window_minutes'],
            ['value' => '30', 'value_type' => 'integer', 'is_public' => false]
        );

        $group = ForumGroup::query()->create([
            'name' => 'Members',
            'slug' => 'members-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Paid Support',
            'slug' => 'paid-support-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PAID_ONLY,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create([
            'membership_expires_at' => now()->addDay(),
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Paid Access Thread',
            'slug' => 'paid-access-thread-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Paid forum reply',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $author->forceFill([
            'membership_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($author)
            ->get(route('posts.edit', $post))
            ->assertForbidden();

        $this->actingAs($author)
            ->put(route('posts.update', $post), [
                'body' => 'Attempted paid forum edit',
            ])
            ->assertForbidden();

        $this->assertSame('Paid forum reply', $post->fresh()->body);
        $this->assertNull($post->fresh()->edited_at);
    }

    public function test_edit_marker_is_hidden_for_edits_within_grace_period(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'forum_post_edit_window_minutes'],
            ['value' => '30', 'value_type' => 'integer', 'is_public' => false]
        );
        Setting::query()->updateOrCreate(
            ['key' => 'forum_post_edit_grace_period_minutes'],
            ['value' => '5', 'value_type' => 'integer', 'is_public' => false]
        );

        Carbon::setTestNow(now());
        [$thread, $post] = $this->createThreadWithReply([
            'body' => 'Grace period reply',
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        $this->actingAs($post->author)
            ->put(route('posts.update', $post), [
                'body' => 'Grace period update',
                'return_to' => route('threads.show', $thread),
            ])
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $this->actingAs($post->author)
            ->get(route('threads.show', $thread))
            ->assertDontSee('[edited]', false)
            ->assertSee('Grace period update');

        Carbon::setTestNow();
    }

    public function test_staff_user_can_move_post_to_another_thread_and_action_is_logged(): void
    {
        [$sourceThread, $post] = $this->createThreadWithReply([
            'body' => 'Post to be moved',
        ]);
        [$destinationThread] = $this->createThreadWithReply();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'move',
            'destination_thread_id' => $destinationThread->id,
            'reason' => 'Belongs in another thread',
            'return_to' => route('threads.show', $sourceThread),
        ]);

        $response->assertRedirect(route('threads.show', $destinationThread).'#post-'.$post->id);
        $response->assertSessionHas('thread_status');

        $movedPost = $post->fresh();
        $this->assertSame($destinationThread->id, (int) $movedPost->thread_id);

        $this->assertDatabaseHas('post_moderation_logs', [
            'thread_id' => $sourceThread->id,
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'action' => 'move',
            'reason' => 'Belongs in another thread',
        ]);
    }

    public function test_thread_starter_post_can_be_moved_leaving_replies_behind(): void
    {
        [$sourceThread] = $this->createThreadWithReply();
        [$destinationThread] = $this->createThreadWithReply();

        $starterPost = Post::query()
            ->where('thread_id', $sourceThread->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->firstOrFail();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('posts.moderate', $starterPost), [
            'action' => 'move',
            'destination_thread_id' => $destinationThread->id,
            'reason' => 'Belongs there',
        ]);

        $response->assertRedirect();
        $this->assertSame($destinationThread->id, (int) $starterPost->fresh()->thread_id);
        // A reply remained, so the source thread stays.
        $this->assertDatabaseHas('threads', ['id' => $sourceThread->id]);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $starterPost->id,
            'action' => 'move',
        ]);
    }

    public function test_move_succeeds_without_a_reason(): void
    {
        [$sourceThread, $post] = $this->createThreadWithReply();
        [$destinationThread] = $this->createThreadWithReply();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'move',
            'destination_thread_id' => $destinationThread->id,
        ]);

        $response->assertRedirect(route('threads.show', $destinationThread).'#post-'.$post->id);
        $response->assertSessionHas('thread_status');
        $this->assertSame($destinationThread->id, (int) $post->fresh()->thread_id);

        $this->assertDatabaseHas('post_moderation_logs', [
            'thread_id' => $sourceThread->id,
            'post_id' => $post->id,
            'action' => 'move',
            'reason' => null,
        ]);
    }

    public function test_staff_user_can_promote_post_to_new_thread_in_another_forum_and_logs_reason(): void
    {
        [$sourceThread, $post] = $this->createThreadWithReply([
            'body' => 'Post to be promoted',
        ]);
        [$otherThread] = $this->createThreadWithReply();
        $destinationForum = $otherThread->forum;

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'promote',
            'destination_forum_id' => $destinationForum->id,
            'new_thread_title' => 'Re: Post Moderation Thread',
            'reason' => 'Deserves its own thread',
            'return_to' => route('threads.show', $sourceThread),
        ]);

        $promotedPost = $post->fresh();
        $newThread = Thread::query()->findOrFail($promotedPost->thread_id);

        $response->assertRedirect(route('threads.show', $newThread).'#post-'.$post->id);
        $response->assertSessionHas('thread_status');

        $this->assertNotSame($sourceThread->id, $newThread->id);
        $this->assertSame($destinationForum->id, (int) $newThread->forum_id);
        $this->assertSame('Re: Post Moderation Thread', $newThread->title);
        $this->assertSame((int) $post->author_id, (int) $newThread->author_id);

        $this->assertDatabaseHas('post_moderation_logs', [
            'thread_id' => $sourceThread->id,
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'action' => 'promote',
            'reason' => 'Deserves its own thread',
        ]);
        $this->assertDatabaseMissing('thread_subscriptions', [
            'thread_id' => $newThread->id,
            'user_id' => $post->author_id,
        ]);
    }

    public function test_thread_starter_post_can_be_promoted_leaving_replies_behind(): void
    {
        [$sourceThread] = $this->createThreadWithReply();
        [$otherThread] = $this->createThreadWithReply();

        $starterPost = Post::query()
            ->where('thread_id', $sourceThread->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->firstOrFail();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('posts.moderate', $starterPost), [
            'action' => 'promote',
            'destination_forum_id' => $otherThread->forum_id,
            'new_thread_title' => 'Split out',
        ]);

        $response->assertRedirect();
        $newThreadId = (int) $starterPost->fresh()->thread_id;
        $this->assertNotSame($sourceThread->id, $newThreadId);
        $this->assertSame('Split out', Thread::query()->find($newThreadId)->title);
        // A reply remained, so the source thread stays.
        $this->assertDatabaseHas('threads', ['id' => $sourceThread->id]);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $starterPost->id,
            'action' => 'promote',
        ]);
    }

    public function test_promote_requires_destination_forum_and_new_thread_title(): void
    {
        [, $post] = $this->createThreadWithReply();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'promote',
        ]);

        $response->assertSessionHasErrors(['destination_forum_id', 'new_thread_title']);
        $response->assertSessionDoesntHaveErrors(['reason']);
    }

    public function test_promote_succeeds_without_a_reason(): void
    {
        [$sourceThread, $post] = $this->createThreadWithReply();
        [$otherThread] = $this->createThreadWithReply();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'promote',
            'destination_forum_id' => $otherThread->forum_id,
            'new_thread_title' => 'Promoted without a reason',
        ]);

        $newThread = Thread::query()->findOrFail($post->fresh()->thread_id);
        $response->assertRedirect(route('threads.show', $newThread).'#post-'.$post->id);
        $response->assertSessionHas('thread_status');

        $this->assertDatabaseHas('post_moderation_logs', [
            'thread_id' => $sourceThread->id,
            'post_id' => $post->id,
            'action' => 'promote',
            'reason' => null,
        ]);
    }

    public function test_staff_user_can_search_threads_for_move(): void
    {
        [$thread] = $this->createThreadWithReply();
        $thread->title = 'Searchable Widget Discussion';
        $thread->save();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)
            ->getJson(route('threads.search-for-move', ['q' => 'Widget']));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $thread->id, 'title' => 'Searchable Widget Discussion']);
    }

    public function test_search_threads_for_move_excludes_the_source_thread_of_given_post(): void
    {
        [$sourceThread, $post] = $this->createThreadWithReply();
        $sourceThread->title = 'Excludable Gadget Thread';
        $sourceThread->save();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)
            ->getJson(route('threads.search-for-move', ['q' => 'Gadget', 'exclude_post_id' => $post->id]));

        $response->assertOk();
        $response->assertJsonMissing(['id' => $sourceThread->id]);
    }

    public function test_search_threads_for_move_is_staff_only(): void
    {
        [$thread] = $this->createThreadWithReply();
        $member = User::factory()->create();

        $response = $this->actingAs($member)
            ->getJson(route('threads.search-for-move', ['q' => $thread->title]));

        $response->assertStatus(403);
        $response->assertJson(['data' => []]);
    }

    public function test_promote_to_thread_button_is_staff_only(): void
    {
        [$thread] = $this->createThreadWithReply();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');
        $member = User::factory()->create();

        $staffResponse = $this->actingAs($moderator)->get(route('threads.show', $thread));
        $staffResponse->assertOk();
        $staffResponse->assertSee('Promote to thread');

        $memberResponse = $this->actingAs($member)->get(route('threads.show', $thread));
        $memberResponse->assertOk();
        $memberResponse->assertDontSee('Promote to thread');
    }

    public function test_promote_forum_picker_is_populated_even_with_staff_frontend_preview_off(): void
    {
        [$thread] = $this->createThreadWithReply();
        [$otherThread] = $this->createThreadWithReply();
        $destinationForum = $otherThread->forum;

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('Promote to thread');
        $response->assertSee($destinationForum->name);
    }

    public function test_staff_can_approve_and_reject_moderated_post(): void
    {
        [$thread, $post] = $this->createThreadWithReply([
            'body' => 'Awaiting review',
            'moderation_status' => ContentModerationStatus::PENDING,
            'approved_at' => null,
        ]);
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'approve',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $post->refresh();
        $thread->refresh();
        $this->assertSame(ContentModerationStatus::APPROVED, $post->moderation_status);
        $this->assertSame(1, (int) $thread->reply_count);

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'reject',
            'reason' => 'Not ready',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $post->refresh();
        $thread->refresh();
        $this->assertSame(ContentModerationStatus::REJECTED, $post->moderation_status);
        $this->assertSame('Not ready', $post->moderation_reason);
        $this->assertSame(0, (int) $thread->reply_count);
    }

    public function test_staff_can_unapprove_a_post_back_into_the_moderation_queue(): void
    {
        [$thread, $post] = $this->createThreadWithReply(['body' => 'Needs a second look']);
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get(route('threads.show', $thread))
            ->assertSee('Unapprove (send to moderation)');

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'unapprove',
            'reason' => 'Checking the links',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $post->refresh();
        $thread->refresh();
        $this->assertSame(ContentModerationStatus::PENDING, $post->moderation_status);
        $this->assertNull($post->approved_at);
        $this->assertSame('Checking the links', $post->moderation_reason);
        $this->assertSame(0, (int) $thread->reply_count);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'action' => 'unapprove',
        ]);

        [, $queuedPosts] = app(ForumModerationService::class)->getReviewQueue();
        $this->assertTrue($queuedPosts->contains('id', $post->id));
    }

    public function test_non_staff_user_cannot_unapprove_a_post(): void
    {
        [$thread, $post] = $this->createThreadWithReply(['body' => 'Fine as is']);
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('threads.show', $thread))
            ->assertDontSee('Unapprove (send to moderation)');

        $this->actingAs($member)->post(route('posts.moderate', $post), [
            'action' => 'unapprove',
        ])->assertForbidden();

        $this->assertSame(ContentModerationStatus::APPROVED, $post->refresh()->moderation_status);
    }

    public function test_staff_can_reject_a_post_permanently_and_it_leaves_the_moderation_queue(): void
    {
        [$thread, $post] = $this->createThreadWithReply([
            'body' => 'Spam reply',
            'moderation_status' => ContentModerationStatus::REJECTED,
        ]);
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        [, $queuedPostsBefore] = app(ForumModerationService::class)->getReviewQueue();
        $this->assertTrue($queuedPostsBefore->contains('id', $post->id));

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'reject_permanently',
            'reason' => 'Spam, will not be reconsidered.',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $post->refresh();
        $this->assertSame(ContentModerationStatus::REJECTED_FINAL, $post->moderation_status);
        $this->assertSame('Spam, will not be reconsidered.', $post->moderation_reason);
        $this->assertTrue($post->isRejected());

        [, $queuedPostsAfter] = app(ForumModerationService::class)->getReviewQueue();
        $this->assertFalse($queuedPostsAfter->contains('id', $post->id));
    }

    public function test_rejecting_a_post_can_also_ban_its_author_permanently(): void
    {
        [$thread, $post] = $this->createThreadWithReply([
            'body' => 'Spam reply',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'reject',
            'ban' => '1',
            'ban_duration' => 'permanent',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $author = $post->author()->firstOrFail();
        $this->assertSame(ContentModerationStatus::REJECTED, $post->refresh()->moderation_status);
        $this->assertTrue((bool) $author->is_banned);
        $this->assertNull($author->banned_until);
    }

    public function test_custom_length_ban_on_reject_requires_an_end_date(): void
    {
        [, $post] = $this->createThreadWithReply([
            'body' => 'Spam reply',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'reject',
            'ban' => '1',
            'ban_duration' => 'custom',
        ])->assertSessionHasErrors('ban_until');

        $this->assertSame(ContentModerationStatus::PENDING, $post->refresh()->moderation_status);
        $this->assertFalse((bool) $post->author()->firstOrFail()->is_banned);
    }

    /**
     * @param  array<string, mixed>  $postOverrides
     * @return array{0: Thread, 1: Post}
     */
    private function createThreadWithReply(array $postOverrides = []): array
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
            'title' => 'Post Moderation Thread',
            'slug' => 'post-moderation-thread-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $replyAuthor = User::factory()->create();

        $replyTimestampOverrides = [];
        foreach (['created_at', 'updated_at'] as $timestampColumn) {
            if (array_key_exists($timestampColumn, $postOverrides)) {
                $replyTimestampOverrides[$timestampColumn] = $postOverrides[$timestampColumn];
                unset($postOverrides[$timestampColumn]);
            }
        }

        $reply = Post::query()->create(array_merge([
            'thread_id' => $thread->id,
            'author_id' => $replyAuthor->id,
            'body' => 'Reply post',
            'is_deleted' => false,
        ], $postOverrides));

        if ($replyTimestampOverrides !== []) {
            $reply->timestamps = false;
            $reply->forceFill($replyTimestampOverrides)->save();
            $reply->timestamps = true;
        }

        return [$thread, $reply];
    }
}
