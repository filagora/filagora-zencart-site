<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ForumModerationService;
use App\Support\StaffFrontendPreview;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadRead;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ThreadModerationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_user_can_lock_and_unlock_thread(): void
    {
        [$thread] = $this->createPublicThread();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $lockResponse = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'lock',
            'return_to' => route('threads.show', $thread),
        ]);

        $lockResponse->assertRedirect(route('threads.show', $thread));
        $lockResponse->assertSessionHas('thread_status', __('forum.flash.thread_locked'));
        $this->assertTrue((bool) $thread->fresh()->is_locked);

        $unlockResponse = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'unlock',
            'return_to' => route('threads.show', $thread),
        ]);

        $unlockResponse->assertRedirect(route('threads.show', $thread));
        $unlockResponse->assertSessionHas('thread_status', __('forum.flash.thread_unlocked'));
        $this->assertFalse((bool) $thread->fresh()->is_locked);
    }

    public function test_staff_user_can_pin_and_unpin_thread(): void
    {
        [$thread] = $this->createPublicThread();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $pinResponse = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'pin',
            'return_to' => route('threads.show', $thread),
        ]);

        $pinResponse->assertRedirect(route('threads.show', $thread));
        $pinResponse->assertSessionHas('thread_status', __('forum.flash.thread_pinned'));
        $this->assertTrue((bool) $thread->fresh()->is_pinned);

        $unpinResponse = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'unpin',
            'return_to' => route('threads.show', $thread),
        ]);

        $unpinResponse->assertRedirect(route('threads.show', $thread));
        $unpinResponse->assertSessionHas('thread_status', __('forum.flash.thread_unpinned'));
        $this->assertFalse((bool) $thread->fresh()->is_pinned);
    }

    public function test_staff_user_can_mark_a_thread_as_unread(): void
    {
        [$thread] = $this->createPublicThread();
        $moderator = User::factory()->create();
        $moderator->forceFill(['forum_read_baseline_at' => now()->addDay()])->save();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        ThreadRead::query()->create([
            'user_id' => $moderator->id,
            'thread_id' => $thread->id,
            'last_read_at' => now(),
        ]);

        $response = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'mark_unread',
            'return_to' => route('forums.show', $thread->forum, false),
        ]);

        // Redirects back to the forum's thread list, same as every other
        // thread action — safe because viewing that list no longer records
        // a read as a side effect (see ForumHeaderNavigationTest).
        $response->assertRedirect(route('forums.show', $thread->forum));
        $response->assertSessionHas('thread_status', __('forum.flash.thread_marked_unread'));

        $this->assertDatabaseHas('thread_reads', [
            'user_id' => $moderator->id,
            'thread_id' => $thread->id,
            'is_unread_override' => true,
        ]);

        $this->actingAs($moderator)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertSee($thread->title);

        $this->actingAs($moderator)->post(route('forums.mark-all-read'));
        $this->assertDatabaseHas('thread_reads', [
            'user_id' => $moderator->id,
            'thread_id' => $thread->id,
            'is_unread_override' => false,
        ]);
        $this->actingAs($moderator)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertDontSee($thread->title);
    }

    public function test_staff_user_sees_mark_unread_button_in_forum_thread_list(): void
    {
        [$thread] = $this->createPublicThread();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertSee('Mark unread')
            ->assertSee('value="mark_unread"', false);
    }

    public function test_lock_action_is_logged(): void
    {
        [$thread] = $this->createPublicThread();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $lockResponse = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'lock',
            'return_to' => route('threads.show', $thread),
        ]);

        $lockResponse->assertRedirect(route('threads.show', $thread));

        $this->assertDatabaseHas('thread_moderation_logs', [
            'thread_id' => $thread->id,
            'moderator_id' => $moderator->id,
            'action' => 'lock',
            'reason' => null,
        ]);
    }

    public function test_staff_user_can_move_thread_to_another_forum_and_logs_reason(): void
    {
        [$thread, $forum] = $this->createPublicThread();
        $destinationForum = Forum::query()->create([
            'forum_group_id' => $forum->forum_group_id,
            'name' => 'Destination Forum',
            'slug' => 'destination-forum-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'move',
            'forum_id' => $destinationForum->id,
            'reason' => 'Move to correct category',
            'return_to' => route('threads.show', $thread),
        ]);

        $response->assertRedirect(route('threads.show', $thread));
        $response->assertSessionHas('thread_status');
        $this->assertSame($destinationForum->id, (int) $thread->fresh()->forum_id);

        $this->assertDatabaseHas('thread_moderation_logs', [
            'thread_id' => $thread->id,
            'moderator_id' => $moderator->id,
            'action' => 'move',
            'reason' => 'Move to correct category',
        ]);
    }

    public function test_staff_user_can_rename_thread_and_slug_updates(): void
    {
        [$thread] = $this->createPublicThread(['title' => 'Breadcrumbs show HTML Entities']);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'rename',
            'title' => 'SOLVED: Breadcrumbs show HTML Entities',
            'return_to' => route('threads.show', $thread),
        ]);

        $response->assertRedirect(route('threads.show', $thread));
        $response->assertSessionHas('thread_status', __('forum.flash.thread_renamed'));

        $thread->refresh();
        $this->assertSame('SOLVED: Breadcrumbs show HTML Entities', $thread->title);
        $this->assertSame('solved-breadcrumbs-show-html-entities', $thread->slug);

        $this->assertDatabaseHas('thread_moderation_logs', [
            'thread_id' => $thread->id,
            'moderator_id' => $moderator->id,
            'action' => 'rename',
            'reason' => 'Renamed from "Breadcrumbs show HTML Entities" to "SOLVED: Breadcrumbs show HTML Entities"',
        ]);
    }

    public function test_renaming_thread_to_the_same_title_keeps_its_existing_slug(): void
    {
        [$thread] = $this->createPublicThread(['title' => 'Same Title', 'slug' => 'same-title-custom-slug']);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'rename',
            'title' => 'Same Title',
            'return_to' => route('threads.show', $thread),
        ]);

        $response->assertRedirect(route('threads.show', $thread));

        $thread->refresh();
        $this->assertSame('Same Title', $thread->title);
        $this->assertSame('same-title-custom-slug', $thread->slug);
    }

    public function test_non_staff_user_cannot_rename_thread(): void
    {
        [$thread] = $this->createPublicThread(['title' => 'Original Title']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.moderate', $thread), [
            'action' => 'rename',
            'title' => 'Hijacked Title',
            'return_to' => route('threads.show', $thread),
        ]);

        $response->assertForbidden();
        $this->assertSame('Original Title', $thread->fresh()->title);
    }

    public function test_forum_listing_offers_the_relocate_dialog_move_control_to_staff_only(): void
    {
        [$thread, $forum] = $this->createPublicThread();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $regularUser = User::factory()->create();

        $staffResponse = $this->actingAs($staff)->get(route('forums.show', $forum));
        $staffResponse->assertOk();
        $staffResponse->assertSee('data-relocate-dialog', false);
        $staffResponse->assertSee('data-relocate-open="thread-move"', false);
        $staffResponse->assertSee('data-thread-moderate-url="'.route('threads.moderate', $thread).'"', false);

        $regularResponse = $this->actingAs($regularUser)->get(route('forums.show', $forum));
        $regularResponse->assertOk();
        $regularResponse->assertDontSee('data-relocate-open="thread-move"', false);
        $regularResponse->assertDontSee('data-relocate-dialog', false);
    }

    public function test_non_staff_user_cannot_moderate_thread(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.moderate', $thread), [
            'action' => 'lock',
        ]);

        $response->assertForbidden();
        $this->assertFalse((bool) $thread->fresh()->is_locked);
    }

    public function test_thread_and_forum_pages_show_lock_controls_only_for_staff(): void
    {
        [$thread, $forum] = $this->createPublicThread();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $regularUser = User::factory()->create();

        $staffThreadResponse = $this->actingAs($staff)->get(route('threads.show', $thread));
        $staffThreadResponse->assertOk();
        $staffThreadResponse->assertSee('Lock Thread');
        $staffThreadResponse->assertSee('Pin Thread');

        $staffForumResponse = $this->actingAs($staff)->get(route('forums.show', $forum));
        $staffForumResponse->assertOk();
        $staffForumResponse->assertSee(route('threads.moderate', $thread));

        $regularThreadResponse = $this->actingAs($regularUser)->get(route('threads.show', $thread));
        $regularThreadResponse->assertOk();
        $regularThreadResponse->assertDontSee('Lock Thread');
        $regularThreadResponse->assertDontSee('Pin Thread');

        $regularForumResponse = $this->actingAs($regularUser)->get(route('forums.show', $forum));
        $regularForumResponse->assertOk();
        $regularForumResponse->assertDontSee(route('threads.moderate', $thread));
    }

    public function test_thread_page_shows_moderation_history_panel_for_staff_only(): void
    {
        [$thread] = $this->createPublicThread();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $regularUser = User::factory()->create();

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'lock',
            'reason' => 'Historical clean-up',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread));

        $staffResponse = $this->actingAs($staff)->get(route('threads.show', $thread));
        $staffResponse->assertOk();
        $staffResponse->assertSee('Moderation History');
        $staffResponse->assertSee('Lock');
        $staffResponse->assertSee('Historical clean-up');

        $regularResponse = $this->actingAs($regularUser)->get(route('threads.show', $thread));
        $regularResponse->assertOk();
        $regularResponse->assertDontSee('Moderation History');
        $regularResponse->assertDontSee('Historical clean-up');
    }

    public function test_staff_preview_mode_hides_thread_moderation_controls_and_history(): void
    {
        [$thread, $forum] = $this->createPublicThread();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'pin',
            'reason' => 'Historical clean-up',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread));

        $threadResponse = $this->actingAs($staff)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('threads.show', $thread));
        $threadResponse->assertOk();
        $threadResponse->assertDontSee('Lock Thread');
        $threadResponse->assertDontSee('Pin Thread');
        $threadResponse->assertDontSee('Move Thread');
        $threadResponse->assertDontSee('View Moderation History');
        $threadResponse->assertDontSee('Historical clean-up');

        $forumResponse = $this->actingAs($staff)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('forums.show', $forum));
        $forumResponse->assertOk();
        $forumResponse->assertDontSee(route('threads.moderate', $thread), false);
        $forumResponse->assertDontSee('data-relocate-open="thread-move"', false);
    }

    public function test_staff_can_create_thread_as_unapproved(): void
    {
        [, $forum] = $this->createPublicThread();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $this->actingAs($staff)
            ->get(route('threads.create.form', $forum))
            ->assertOk()
            ->assertSee('Post as unapproved')
            ->assertSee('name="post_as_unapproved"', false);

        $response = $this->actingAs($staff)->post(route('threads.create', $forum), [
            'title' => 'Moderator Pending Thread',
            'body' => 'Created for the moderation queue.',
            'post_as_unapproved' => '1',
        ]);

        $thread = Thread::query()
            ->where('title', 'Moderator Pending Thread')
            ->firstOrFail();
        $post = Post::query()
            ->where('thread_id', $thread->id)
            ->firstOrFail();

        $response->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
        $response->assertSessionHas('thread_status', __('forum.flash.thread_submitted'));

        $this->assertSame(ContentModerationStatus::PENDING, $thread->moderation_status);
        $this->assertSame(ContentModerationStatus::PENDING, $post->moderation_status);
        $this->assertNull($thread->approved_at);
        $this->assertNull($post->approved_at);
    }

    public function test_staff_can_create_a_thread_locked(): void
    {
        [, $forum] = $this->createPublicThread();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $this->actingAs($staff)
            ->get(route('threads.create.form', $forum))
            ->assertOk()
            ->assertSee('Lock thread')
            ->assertSee('name="lock_thread"', false);

        $this->actingAs($staff)->post(route('threads.create', $forum), [
            'title' => 'Announcement Thread',
            'body' => 'Replies are closed on purpose.',
            'lock_thread' => '1',
        ]);

        $thread = Thread::query()->where('title', 'Announcement Thread')->firstOrFail();

        $this->assertTrue((bool) $thread->is_locked);
        $this->assertSame(ContentModerationStatus::APPROVED, $thread->moderation_status);
    }

    public function test_regular_user_cannot_create_a_locked_thread(): void
    {
        [, $forum] = $this->createPublicThread();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('threads.create.form', $forum))
            ->assertOk()
            ->assertDontSee('Lock thread')
            ->assertDontSee('name="lock_thread"', false);

        $this->actingAs($user)->post(route('threads.create', $forum), [
            'title' => 'Regular Unlocked Thread',
            'body' => 'A normal thread that stays open.',
            'lock_thread' => '1',
        ]);

        $thread = Thread::query()->where('title', 'Regular Unlocked Thread')->firstOrFail();

        $this->assertFalse((bool) $thread->is_locked);
    }

    public function test_regular_user_cannot_force_new_thread_to_be_unapproved(): void
    {
        [, $forum] = $this->createPublicThread();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('threads.create.form', $forum))
            ->assertOk()
            ->assertDontSee('Post as unapproved')
            ->assertDontSee('name="post_as_unapproved"', false);

        $this->actingAs($user)->post(route('threads.create', $forum), [
            'title' => 'Regular Approved Thread',
            'body' => 'This should follow normal forum approval rules.',
            'post_as_unapproved' => '1',
        ]);

        $thread = Thread::query()
            ->where('title', 'Regular Approved Thread')
            ->firstOrFail();
        $post = Post::query()
            ->where('thread_id', $thread->id)
            ->firstOrFail();

        $this->assertSame(ContentModerationStatus::APPROVED, $thread->moderation_status);
        $this->assertSame(ContentModerationStatus::APPROVED, $post->moderation_status);
        $this->assertSame($user->id, $thread->approved_by_id);
        $this->assertSame($user->id, $post->approved_by_id);
    }

    public function test_thread_moderation_state_badges_are_visible_on_forum_and_thread_pages(): void
    {
        [$thread, $forum] = $this->createPublicThread([
            'is_pinned' => true,
            'is_locked' => true,
        ]);

        $user = User::factory()->create();

        $forumResponse = $this->actingAs($user)->get(route('forums.show', $forum));
        $forumResponse->assertOk();
        $forumResponse->assertSee('Sticky');
        $forumResponse->assertSee('Locked');

        $threadResponse = $this->actingAs($user)->get(route('threads.show', $thread));
        $threadResponse->assertOk();
        $threadResponse->assertSee('Sticky');
        $threadResponse->assertSee('Locked');
        $threadResponse->assertSee('This thread is locked. New replies are disabled.');
    }

    public function test_plain_reject_is_no_longer_a_thread_moderation_action(): void
    {
        [$thread] = $this->createPublicThread([
            'moderation_status' => ContentModerationStatus::PENDING,
            'approved_at' => null,
        ]);

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'reject',
        ])->assertSessionHasErrors('action');

        $this->assertSame(ContentModerationStatus::PENDING, $thread->refresh()->moderation_status);
    }

    public function test_rejecting_a_queued_thread_permanently_deletes_it_and_its_posts(): void
    {
        [$thread, $forum] = $this->createPublicThread([
            'moderation_status' => ContentModerationStatus::PENDING,
            'approved_at' => null,
        ]);
        $replier = User::factory()->create();
        $reply = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $replier->id,
            'body' => 'A reply that goes with the thread.',
        ]);
        $this->assertSame(1, (int) $replier->refresh()->forum_posts_count);

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'reject_permanently',
            'reason' => 'Spam, will not be reconsidered.',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('forums.show', $forum))
            ->assertSessionHas('thread_status', __('forum.flash.thread_deleted'));

        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('posts', ['id' => $reply->id]);
        $this->assertSame(0, (int) $replier->refresh()->forum_posts_count);
        $this->assertFalse((bool) $thread->author()->firstOrFail()->is_banned);

        [$queuedThreads] = app(ForumModerationService::class)->getReviewQueue();
        $this->assertFalse($queuedThreads->contains('id', $thread->id));
    }

    public function test_staff_can_delete_an_approved_thread_from_the_thread_menu(): void
    {
        [$thread, $forum] = $this->createPublicThread();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $regularUser = User::factory()->create();

        $this->actingAs($staff)->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee(__('forum.thread_delete.open'))
            ->assertSee('data-thread-delete-open', false)
            ->assertSee('data-thread-delete-dialog', false)
            ->assertSee('name="ban" value="1" class="mt-1" data-thread-delete-ban-checkbox', false);

        $this->actingAs($regularUser)->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee(__('forum.thread_delete.open'))
            ->assertDontSee('data-thread-delete-dialog', false);

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'reject_permanently',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('forums.show', $forum));

        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
    }

    public function test_approving_a_thread_only_triggers_one_full_forum_stats_resync(): void
    {
        $this->markTestSkipped('The stats recompute during approval belongs to the forums package and is tested there.');

        // Regression test: moderateThread() used to save the thread directly
        // (letting SyncForumStatsObserver react with its own full resync)
        // and then call syncThreadDerivedFields(), which itself both saved
        // the thread again (a second observer-triggered resync) and made an
        // explicit third resync call — the same expensive full-forum query
        // running up to three times for one moderation click. A partial spy
        // on ForumStatsService (real behavior preserved, calls recorded)
        // lets this assert the expensive method only actually ran once.
        [$thread] = $this->createPublicThread([
            'moderation_status' => ContentModerationStatus::PENDING,
            'approved_at' => null,
        ]);

        $starterPost = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $starterPost->update([
            'moderation_status' => ContentModerationStatus::PENDING,
            'approved_at' => null,
        ]);

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $statsServiceSpy = Mockery::spy(ForumStatsService::class)->makePartial();
        $this->app->instance(ForumStatsService::class, $statsServiceSpy);

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'approve',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread));

        $statsServiceSpy->shouldHaveReceived('syncForForumIds')->once();

        $thread->refresh();
        $this->assertSame(ContentModerationStatus::APPROVED, $thread->moderation_status);
    }

    public function test_deleting_a_thread_bans_its_author_permanently_whatever_duration_is_sent(): void
    {
        [$thread, $forum] = $this->createPublicThread([
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);
        $author = $thread->author()->firstOrFail();

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'reject_permanently',
            'reason' => 'Spam',
            'ban' => '1',
            'ban_reason' => 'Spammer',
            'ban_duration' => '7d',
        ])->assertRedirect(route('forums.show', $forum))
            ->assertSessionHas('thread_status', fn (string $status): bool => str_contains($status, 'has been banned.'));

        $author->refresh();
        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
        $this->assertTrue((bool) $author->is_banned);
        $this->assertSame('Spammer', $author->ban_reason);
        $this->assertNull($author->banned_until);
    }

    public function test_ban_fields_are_ignored_when_a_thread_is_approved(): void
    {
        [$thread] = $this->createPublicThread([
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $this->actingAs($staff)->post(route('threads.moderate', $thread), [
            'action' => 'approve',
            'ban' => '1',
            'ban_duration' => 'permanent',
        ])->assertRedirect(route('threads.show', $thread));

        $this->assertFalse((bool) $thread->author()->firstOrFail()->is_banned);
    }

    /**
     * @return array{0: Thread, 1: Forum}
     */
    private function createPublicThread(array $threadOverrides = [], array $forumOverrides = []): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create(array_merge([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ], $forumOverrides));

        $author = User::factory()->create();
        $thread = Thread::query()->create(array_merge([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Moderation Thread',
            'slug' => 'moderation-thread-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
            'is_locked' => false,
        ], $threadOverrides));

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        return [$thread, $forum];
    }
}
