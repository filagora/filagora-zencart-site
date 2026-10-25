<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalTime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filagora\Cms\Models\CmsNavigation;
use Filagora\Entitlements\Enums\DefaultEntitlementTarget;
use Filagora\Entitlements\Models\DefaultEntitlementGrant;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\PostAttachment;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ForumHeaderNavigationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_forum_pages_render_forum_header_navigation_from_cms_records(): void
    {
        $this->markTestSkipped('Needs the new-thread form: slice S3 (forum writing).');

        CmsNavigation::query()->updateOrCreate(
            ['name' => 'forum_header'],
            ['items' => [
                ['label' => "Today's Posts", 'url' => '/forums/today', 'target' => '_self'],
                ['label' => 'Subscriptions', 'url' => '/profile?tab=subscriptions', 'target' => '_self'],
            ]]
        );

        $forum = $this->createForum();
        $author = User::factory()->create();
        $thread = $this->createThreadWithPost($forum, $author, 'Nav Coverage Thread', now());

        $this->get(route('forums.index'))
            ->assertOk()
            ->assertSee("Today's Posts")
            ->assertSee('/forums/today', false)
            ->assertSee('Subscriptions')
            ->assertSee('/profile?tab=subscriptions', false);

        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee("Today's Posts")
            ->assertSee('Subscriptions');

        // The same shortcut bar now rides along on the thread, new-thread and
        // edit-post pages, not just the forum listing pages.
        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee("Today's Posts")
            ->assertSee('Subscriptions');

        $this->actingAs($author)
            ->get(route('threads.create.form', $forum))
            ->assertOk()
            ->assertSee("Today's Posts");

        $this->actingAs($author)
            ->get(route('posts.edit', $thread->posts()->first()))
            ->assertOk()
            ->assertSee("Today's Posts");
    }

    public function test_forum_header_forum_participant_links_render_for_verified_users(): void
    {
        DefaultEntitlementGrant::query()->create([
            'entitlement_key' => 'forums.standard.access',
            'target_type' => DefaultEntitlementTarget::VERIFIED_USERS,
        ]);

        CmsNavigation::query()->updateOrCreate(
            ['name' => 'forum_header'],
            ['items' => [
                ['label' => "Today's Posts", 'url' => '/forums/today', 'target' => '_self'],
                ['label' => 'Unread Posts', 'url' => '/forums/unread', 'target' => '_self', 'entitlements' => ['forums.standard.access']],
                ['label' => 'Mark All Read', 'url' => '/forums/mark-all-read', 'target' => '_self', 'entitlements' => ['forums.standard.access']],
            ]]
        );

        $forum = $this->createForum();

        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee("Today's Posts")
            ->assertDontSee('Unread Posts');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Unread Posts')
            ->assertSee('/forums/unread', false)
            ->assertSee('Mark All Read')
            ->assertSee('action="'.route('forums.mark-all-read').'"', false)
            ->assertDontSee('href="/forums/mark-all-read"', false);
    }

    public function test_last_24_hours_page_only_lists_threads_with_recent_activity(): void
    {
        $frozenNow = now()->startOfDay()->addHours(10);

        try {
            Carbon::setTestNow($frozenNow);

            $forum = $this->createForum();
            $author = User::factory()->create();

            $todayThread = Thread::query()->create([
                'forum_id' => $forum->id,
                'author_id' => $author->id,
                'title' => 'Today Thread',
                'slug' => 'today-thread',
                'moderation_status' => ContentModerationStatus::APPROVED,
                'last_post_at' => now()->subHour(),
            ]);
            Thread::withoutTimestamps(function () use ($todayThread): void {
                $todayThread->forceFill([
                    'created_at' => now()->subHours(2),
                    'updated_at' => now()->subHour(),
                ])->save();
            });

            Post::query()->create([
                'thread_id' => $todayThread->id,
                'author_id' => $author->id,
                'body' => 'Fresh post',
                'moderation_status' => ContentModerationStatus::APPROVED,
            ]);

            $oldThread = Thread::query()->create([
                'forum_id' => $forum->id,
                'author_id' => $author->id,
                'title' => 'Old Thread',
                'slug' => 'old-thread',
                'moderation_status' => ContentModerationStatus::APPROVED,
                'last_post_at' => now()->subDays(2),
            ]);
            Thread::withoutTimestamps(function () use ($oldThread): void {
                $oldThread->forceFill([
                    'created_at' => now()->subDays(3),
                    'updated_at' => now()->subDays(2),
                ])->save();
            });

            Post::query()->create([
                'thread_id' => $oldThread->id,
                'author_id' => $author->id,
                'body' => 'Older post',
                'moderation_status' => ContentModerationStatus::APPROVED,
            ]);

            $this->get(route('forums.today'))
                ->assertOk()
                ->assertSee('Today Thread')
                ->assertSee('Replies:')
                ->assertDontSee('Latest post')
                ->assertDontSeeText('Old Thread');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_last_24_hours_page_includes_a_thread_active_shortly_before_server_midnight(): void
    {
        // Regression for zen-cart.com/threads/207365: the old cutoff was
        // now()->startOfDay(), a server-midnight (UTC) calendar boundary, so
        // a thread active 90 minutes before midnight - well within the last
        // 24 hours - was wrongly excluded the moment the clock ticked over.
        // The fix uses a rolling now()->subHours(24) window instead.
        $frozenNow = now()->startOfDay()->addHour();

        try {
            Carbon::setTestNow($frozenNow);

            $forum = $this->createForum();
            $author = User::factory()->create();

            $thread = Thread::query()->create([
                'forum_id' => $forum->id,
                'author_id' => $author->id,
                'title' => 'Late Night Thread',
                'slug' => 'late-night-thread',
                'moderation_status' => ContentModerationStatus::APPROVED,
                'last_post_at' => now()->subMinutes(90),
            ]);
            Thread::withoutTimestamps(function () use ($thread): void {
                $thread->forceFill([
                    'created_at' => now()->subHours(3),
                    'updated_at' => now()->subMinutes(90),
                ])->save();
            });

            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $author->id,
                'body' => 'Posted shortly before midnight',
                'moderation_status' => ContentModerationStatus::APPROVED,
            ]);

            $this->get(route('forums.today'))
                ->assertOk()
                ->assertSeeText('Late Night Thread');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_forum_show_uses_shared_thread_list_layout(): void
    {
        $forum = $this->createForum();
        $author = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Shared Layout Thread',
            'slug' => 'shared-layout-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'last_post_at' => now(),
        ]);

        $firstPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $latestPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Latest reply',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $thread->forceFill([
            'last_post_author_id' => $author->id,
            'last_post_at' => $latestPost->created_at,
        ])->save();

        $latestPostUrl = route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$latestPost->id;

        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Shared Layout Thread')
            ->assertDontSee('Latest post')
            ->assertSee('Replies:')
            ->assertSee(route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$firstPost->id, false)
            ->assertSee($latestPostUrl, false);
    }

    public function test_forum_show_displays_my_posts_indicator_for_authenticated_participant(): void
    {
        $forum = $this->createForum();
        $author = User::factory()->create();
        $participant = User::factory()->create();
        $participantReplyAt = now()->setDate(2026, 5, 6)->setTime(13, 32, 0);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Participant Indicator Thread',
            'slug' => 'participant-indicator-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'last_post_at' => $participantReplyAt,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $participant->id,
            'body' => 'My reply',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'created_at' => $participantReplyAt,
            'updated_at' => $participantReplyAt,
        ]);

        $this->actingAs($participant)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('data-thread-user-posts-indicator', false)
            ->assertSee('You have 1 post in this thread.', false)
            ->assertSee('Your last post:', false)
            ->assertSee(LocalTime::format($participantReplyAt), false);
    }

    public function test_forum_show_displays_visible_attachment_indicator_and_list(): void
    {
        $forum = $this->createForum();
        $author = User::factory()->create();
        $otherUser = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Attachment Thread',
            'slug' => 'attachment-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'last_post_at' => now(),
        ]);

        $visiblePost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Visible attachment post',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $hiddenPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $otherUser->id,
            'body' => 'Hidden attachment post',
            'moderation_status' => ContentModerationStatus::REJECTED,
        ]);

        PostAttachment::query()->create([
            'legacy_attachment_id' => 1001,
            'legacy_filedata_id' => 2001,
            'post_id' => $visiblePost->id,
            'thread_id' => $thread->id,
            'uploader_id' => $author->id,
            'original_filename' => 'visible-guide.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'filesize_bytes' => 128,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/vbulletin/1001-2001.pdf',
        ]);

        PostAttachment::query()->create([
            'legacy_attachment_id' => 1002,
            'legacy_filedata_id' => 2002,
            'post_id' => $hiddenPost->id,
            'thread_id' => $thread->id,
            'uploader_id' => $otherUser->id,
            'original_filename' => 'hidden-guide.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'filesize_bytes' => 128,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/vbulletin/1002-2002.pdf',
        ]);

        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('data-thread-attachments-indicator', false)
            ->assertSee(route('legacy.attachments.index', $thread), false)
            ->assertSee('This thread has 1 attachment.', false);

        $this->get(route('legacy.attachments.index', $thread))
            ->assertOk()
            ->assertSee('Attachments')
            ->assertSee('Total Attachments: 1')
            ->assertSee('visible-guide.pdf')
            ->assertDontSee('hidden-guide.pdf');
    }

    public function test_unread_posts_page_requires_verified_authenticated_user(): void
    {
        $verifiedUser = User::factory()->create();
        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->get(route('forums.unread'))
            ->assertRedirect(route('verification.notice', ['intended' => '/forums/unread']));

        $this->actingAs($verifiedUser)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertSee('Unread Posts');
    }

    public function test_viewing_thread_marks_it_read_and_removes_it_from_unread_list(): void
    {
        $forum = $this->createForum();
        $user = User::factory()->create();
        $thread = $this->createThreadWithPost($forum, $user, 'Unread Thread', now()->subMinute());

        $this->actingAs($user)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertSee('Unread Thread');

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk();

        $this->assertDatabaseHas('thread_reads', [
            'user_id' => $user->id,
            'thread_id' => $thread->id,
        ]);

        $this->actingAs($user)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertDontSee('Unread Thread');
    }

    public function test_imported_user_read_baseline_hides_older_threads_but_not_newer_threads(): void
    {
        $forum = $this->createForum();
        $author = User::factory()->create();
        $viewer = User::factory()->create([
            'forum_read_baseline_at' => now()->subDay(),
        ]);
        $this->createThreadWithPost($forum, $author, 'Older Imported Thread', now()->subDays(2));
        $this->createThreadWithPost($forum, $author, 'Newer Imported Thread', now()->subHour());

        $this->actingAs($viewer)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertDontSee('Older Imported Thread')
            ->assertSee('Newer Imported Thread');

        $response = $this->actingAs($viewer)->get(route('forums.show', $forum));
        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'data-thread-read-state="unread"'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-thread-read-state="read"'));
    }

    public function test_reading_one_thread_does_not_mark_sibling_threads_in_the_same_forum_read(): void
    {
        // Regression test: showThread() used to also call recordForumRead(),
        // stamping a forum-wide read watermark just because a single thread
        // was opened. Since is_unread_for_user takes the GREATEST of the
        // thread-level and forum-level markers, that silently made every
        // other never-visited thread in the same forum look "read" too.
        $forum = $this->createForum();
        $user = User::factory()->create();
        $threadA = $this->createThreadWithPost($forum, $user, 'Thread A', now()->subDay());
        $threadB = $this->createThreadWithPost($forum, $user, 'Thread B', now()->subDay());

        $this->actingAs($user)
            ->get(route('threads.show', $threadA))
            ->assertOk();

        $this->assertDatabaseMissing('forum_reads', [
            'user_id' => $user->id,
            'forum_id' => $forum->id,
        ]);

        $this->actingAs($user)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertDontSee('Thread A')
            ->assertSee('Thread B');
    }

    public function test_viewing_forum_does_not_mark_current_threads_read_for_that_forum(): void
    {
        $forum = $this->createForum();
        $user = User::factory()->create();
        $thread = $this->createThreadWithPost($forum, $user, 'Forum Read Thread', now()->subMinute());

        $this->actingAs($user)
            ->get(route('forums.show', $forum))
            ->assertOk();

        $this->assertDatabaseMissing('forum_reads', [
            'user_id' => $user->id,
            'forum_id' => $forum->id,
        ]);

        $this->assertDatabaseMissing('thread_reads', [
            'user_id' => $user->id,
            'thread_id' => $thread->id,
        ]);

        $this->actingAs($user)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertSee('Forum Read Thread');
    }

    public function test_today_posts_page_renders_thread_read_state_for_authenticated_user(): void
    {
        $forum = $this->createForum();
        $user = User::factory()->create();
        $this->createThreadWithPost($forum, $user, 'Read State Thread', now());

        $this->actingAs($user)
            ->get(route('forums.today'))
            ->assertOk()
            ->assertSee('Read State Thread')
            ->assertSee('data-thread-read-state', false);
    }

    public function test_forum_show_does_not_mark_threads_read_merely_by_listing_them(): void
    {
        // Regression test: viewing a forum's thread list must not itself
        // record a read — only actually reading a thread (or explicit
        // Mark All Read) should. Otherwise "mark unread" (and the unread
        // indicator generally) is immediately undone the moment you look
        // at the very list meant to show it.
        $forum = $this->createForum();
        $user = User::factory()->create();
        $this->createThreadWithPost($forum, $user, 'Read State Thread', now()->subMinute());

        $this->actingAs($user)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('data-thread-read-state="unread"', false);

        $this->assertDatabaseMissing('forum_reads', [
            'user_id' => $user->id,
            'forum_id' => $forum->id,
        ]);
    }

    public function test_today_posts_page_bolds_the_title_of_unread_threads_for_authenticated_user(): void
    {
        $forum = $this->createForum();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $thread = $this->createThreadWithPost($forum, $author, 'Unread Thread Title', now());

        $response = $this->actingAs($viewer)->get(route('forums.today'));

        $response->assertOk();
        $response->assertSee(
            'class="min-w-0 text-[15px] leading-tight text-[#1a5fd0] hover:underline font-bold"',
            false
        );
    }

    public function test_forum_show_does_not_bold_the_title_of_read_threads_for_authenticated_user(): void
    {
        $forum = $this->createForum();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $thread = $this->createThreadWithPost($forum, $author, 'Read Thread Title', now()->subMinute());

        $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response = $this->actingAs($viewer)->get(route('forums.show', $forum));

        $response->assertOk();
        $response->assertSee(
            'class="min-w-0 text-[15px] leading-tight text-[#1a5fd0] hover:underline font-normal"',
            false
        );
    }

    public function test_forum_show_links_the_latest_post_icon_for_both_read_and_unread_threads(): void
    {
        $forum = $this->createForum();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $unreadThread = $this->createThreadWithPost($forum, $author, 'Unread With Latest Post', now());
        $latestPost = Post::query()->create([
            'thread_id' => $unreadThread->id,
            'author_id' => $author->id,
            'body' => 'Second post',
        ]);
        $readThread = $this->createThreadWithPost($forum, $author, 'Read Thread', now()->subMinute());

        $this->actingAs($viewer)->get(route('threads.show', $readThread));

        $response = $this->actingAs($viewer)->get(route('forums.show', $forum));

        $response->assertOk();
        // The latest-post icon is a plain navigation convenience, not an
        // unread indicator (that's the bold title / read-state badge
        // instead), so it shows for every thread regardless of read state.
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'data-thread-latest-post-link'),
            'Expected a latest-post icon for both the unread and the read thread.'
        );
        // The icon's href points at the *latest* post, which only exists as
        // a link at all because of the icon (the thread title link always
        // points at the *first* post instead).
        $response->assertSee(
            'href="'.route('threads.show', ['thread' => $unreadThread, 'page' => 1]).'#post-'.$latestPost->id.'"',
            false
        );
    }

    public function test_mark_all_read_route_marks_accessible_forums_read_and_clears_unread_threads(): void
    {
        $forum = $this->createForum();
        $user = User::factory()->create();
        $thread = $this->createThreadWithPost($forum, $user, 'Bulk Read Thread', now()->subMinute());

        $this->actingAs($user)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertSee('Bulk Read Thread');

        $this->actingAs($user)
            ->post(route('forums.mark-all-read'))
            ->assertRedirect(route('forums.unread'));

        $this->assertDatabaseHas('forum_reads', [
            'user_id' => $user->id,
            'forum_id' => $forum->id,
        ]);

        $this->assertDatabaseMissing('thread_reads', [
            'user_id' => $user->id,
            'thread_id' => $thread->id,
        ]);

        $this->actingAs($user)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertDontSee('Bulk Read Thread');
    }

    public function test_mark_all_read_get_route_is_not_allowed_and_does_not_write_read_markers(): void
    {
        $forum = $this->createForum();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forums.mark-all-read'))
            ->assertStatus(405);

        $this->assertDatabaseMissing('forum_reads', [
            'user_id' => $user->id,
            'forum_id' => $forum->id,
        ]);
    }

    private function createForum(): Forum
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Announcements',
            'slug' => 'announcements',
            'is_active' => true,
        ]);
    }

    private function createThreadWithPost(Forum $forum, User $author, string $title, CarbonInterface $at): Thread
    {
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'last_post_at' => $at,
        ]);
        Thread::withoutTimestamps(function () use ($thread, $at): void {
            $thread->forceFill([
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();
        });

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Test body',
        ]);

        return $thread;
    }
}
