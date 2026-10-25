<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\Channels\CoalescingDatabaseChannel;
use App\Notifications\ThreadUpdatedNotification;
use Carbon\Carbon;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\Forums\Models\ThreadSubscriptionDigestItem;
use Filagora\Memberships\Enums\MembershipStatus;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ThreadReplyTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_thread_page_shows_reply_and_quote_buttons_for_authenticated_user(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('Reply with Quote');
        $response->assertSee('data-thread-quote', false);
    }

    public function test_reply_composer_starts_in_the_mode_named_by_the_user_preference(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        // The rich composer scaffolding is always present; a user on the
        // default preference starts in rich mode.
        $this->actingAs($user)->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('data-rich-editor data-rich-editor-target="reply-body" data-rich-editor-default="rich"', false)
            ->assertSee('data-markdown-classic-toolbar', false);

        // A user who opts out in their profile starts in Markdown mode; the
        // in-composer checkbox flips it.
        $user->update(['prefers_rich_editor' => false]);
        $this->actingAs($user)->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('data-rich-editor-default="source"', false);
    }

    public function test_thread_page_shows_cancel_button_in_reply_composer_for_authenticated_user(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('data-thread-reply-cancel', false);
        $response->assertSee('>Cancel</button>', false);
    }

    public function test_thread_page_reply_composer_buttons_use_accessible_labels_and_touch_targets(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('role="toolbar"', false);
        $response->assertSee('aria-label="Bold"', false);
        $response->assertSee('aria-label="Insert image"', false);
        $response->assertSee('aria-label="Insert smiley 🙂"', false);
        $response->assertSee('data-smiley="🙂"', false);
        $response->assertSee('aria-label="Insert smiley 👍"', false);
        $response->assertSee('data-smiley="👍"', false);
        $response->assertSee('aria-label="Insert smiley 👎"', false);
        $response->assertSee('data-smiley="👎"', false);
        $response->assertSee('aria-label="Preview reply"', false);
        $response->assertSee('aria-label="Cancel reply"', false);
        $response->assertSee('min-h-10 min-w-10', false);
        $response->assertSee('h-10 w-10', false);
    }

    public function test_thread_page_shows_author_user_title_when_present(): void
    {
        $author = User::factory()->create([
            'username' => 'dustie',
            'user_title' => 'Community Guide',
        ]);
        [$thread] = $this->createPublicThread([
            'author' => $author,
        ]);
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSeeText('dustie');
        $response->assertSeeText('Community Guide');
    }

    public function test_reply_can_store_and_render_a_unicode_smiley(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('threads.reply', $thread), ['body' => 'Thanks 🙂'])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'thread_id' => $thread->id,
            'author_id' => $user->id,
            'body' => 'Thanks 🙂',
        ]);
        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Thanks 🙂');
    }

    public function test_thread_page_shows_author_profile_metadata_sidebar(): void
    {
        $author = User::factory()->create([
            'username' => 'lat9',
            'user_title' => 'Administrator',
            'location' => 'Stuart, FL',
            'created_at' => now()->setDate(2026, 6, 1),
            'legacy_registered_at' => now()->setDate(2009, 9, 15),
        ]);

        [$thread] = $this->createPublicThread([
            'author' => $author,
        ]);
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSeeText('Join Date:');
        $response->assertSeeText('Sep 2009');
        $response->assertSeeText('Location:');
        $response->assertSeeText('Stuart, FL');
        $response->assertSeeText('Posts:');
        $response->assertSeeText('Plugin Contributions:');
        $response->assertSee('alt="lat9 avatar"', false);
    }

    public function test_thread_page_escapes_scriptable_markup_in_post_bodies_and_signatures(): void
    {
        $author = User::factory()->create();
        [$thread] = $this->createPublicThread([
            'author' => $author,
        ]);
        $viewer = User::factory()->create();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $post->update([
            'body' => "<script>alert(\"body\")</script> [bad link](javascript:alert(2))\n\n**Safe body**",
        ]);
        $author->update([
            'signature' => '> <img src=x onerror=alert(3)> Signed\n\n<svg/onload=alert(4)> [unsafe label](javascript:alert(5))',
        ]);

        $response = $this->actingAs($viewer)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(&quot;body&quot;)&lt;/script&gt;', false);
        $response->assertSee('<strong>Safe body</strong>', false);
        $response->assertSee('&lt;img src=x onerror=alert(3)&gt; Signed', false);
        $response->assertDontSee('<script>alert("body")</script>', false);
        $response->assertDontSee('href="javascript:alert(2)"', false);
        $response->assertDontSee('<svg/onload=alert(4)>', false);
        $response->assertDontSee('<img src=x onerror=alert(3)>', false);
    }

    public function test_thread_page_shows_the_authors_current_signature_on_older_posts(): void
    {
        $author = User::factory()->create(['signature' => 'Old signature']);
        [$thread] = $this->createPublicThread(['author' => $author]);
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $post->update(['signature' => 'Old signature']);

        $author->update(['signature' => 'Fresh signature']);

        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Fresh signature')
            ->assertDontSee('Old signature');

        $author->update(['signature' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Fresh signature')
            ->assertDontSee('Old signature');
    }

    public function test_forum_page_shows_start_thread_but_not_reply_action_for_verified_user(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('forums.show', $thread->forum));

        $response->assertOk();
        $response->assertSee('Start New Thread');
        $response->assertDontSee('>Reply<', false);
    }

    public function test_thread_page_uses_flat_issue_list_style_shells(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('class="mb-4 border border-[#d8dce2] bg-white p-4"', false);
        $response->assertSee('class="mt-4 border border-[#d8dce2] bg-white p-4"', false);
        $response->assertSee('class="overflow-hidden border border-[#d8dce2] bg-white"', false);
    }

    public function test_new_thread_form_uses_markdown_editor_controls(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('threads.create.form', $thread->forum));

        $response->assertOk();
        $response->assertSee('aria-label="Markdown tools"', false);
        $response->assertSee('data-markdown-target="thread-body"', false);
        $response->assertSee('data-smiley-target="thread-body" data-smiley="🙂"', false);
        $response->assertSee('data-smiley-target="thread-body" data-smiley="👍"', false);
        $response->assertSee('data-markdown-preview-panel="thread-body-preview"', false);
        $response->assertSee('Uses Markdown', false);
    }

    public function test_moderated_forum_shows_visible_thread_approval_cta(): void
    {
        [$thread] = $this->createPublicThread([], [
            'requires_thread_approval' => true,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertSee('Moderated forum.')
            ->assertSee('New threads are reviewed by staff before they appear publicly.');

        $this->actingAs($user)
            ->get(route('threads.create.form', $thread->forum))
            ->assertOk()
            ->assertSee('Staff approval required.')
            ->assertSee('Submit Thread for Approval');
    }

    public function test_user_flag_can_show_thread_moderation_cta_in_unmoderated_forum(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'moderate_first_forum_post' => true,
        ]);

        $this->actingAs($user)
            ->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertSee('Moderated forum.')
            ->assertSee('New threads and replies are reviewed by staff before they appear publicly.');

        $this->actingAs($user)
            ->get(route('threads.create.form', $thread->forum))
            ->assertOk()
            ->assertSee('Staff approval required.')
            ->assertSee('Submit Thread for Approval');
    }

    public function test_verified_user_can_create_new_thread_from_forum(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'signature' => '[b]Site Team[/b]',
        ]);

        $response = $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'My Fresh Topic',
            'body' => 'Opening post body.',
        ]);

        $createdThread = Thread::query()->where('title', 'My Fresh Topic')->first();
        $this->assertNotNull($createdThread);
        $this->assertNotNull($createdThread->slug);
        $this->assertSame($thread->forum_id, $createdThread->forum_id);
        $this->assertSame($user->id, $createdThread->author_id);

        $createdPost = Post::query()->where('thread_id', $createdThread->id)->first();
        $this->assertNotNull($createdPost);
        $this->assertSame('Opening post body.', $createdPost->body);
        $this->assertSame('markdown', $createdPost->content_format);
        $this->assertSame('[b]Site Team[/b]', $createdPost->signature);

        $thread->forum->refresh();
        $this->assertSame(2, (int) $thread->forum->thread_count);
        $this->assertSame(2, (int) $thread->forum->post_count);
        $this->assertSame((int) $createdPost->id, (int) ($thread->forum->last_post_id ?? 0));

        $response->assertRedirect(route('threads.show', $createdThread).'#post-'.$createdPost->id);
    }

    public function test_new_thread_rejects_a_body_over_the_length_cap(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'Too long',
            'body' => str_repeat('a', 20001),
        ]);

        $response->assertSessionHasErrors(['body']);
        $this->assertNull(Thread::query()->where('title', 'Too long')->first());
    }

    public function test_double_submitting_new_thread_does_not_create_duplicate(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        Carbon::setTestNow(now());

        $first = $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'Resubmitted Topic',
            'body' => 'Same opening post body.',
        ]);
        $createdThread = Thread::query()->where('title', 'Resubmitted Topic')->firstOrFail();
        $createdPost = Post::query()->where('thread_id', $createdThread->id)->firstOrFail();
        $first->assertRedirect(route('threads.show', $createdThread).'#post-'.$createdPost->id);

        // Simulate a double-click/back-button resubmit a few seconds later.
        $second = $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'Resubmitted Topic',
            'body' => 'Same opening post body.',
        ]);

        $this->assertSame(1, Thread::query()->where('title', 'Resubmitted Topic')->count());
        $second->assertRedirect(route('threads.show', $createdThread).'#post-'.$createdPost->id);
        $second->assertSessionHas('thread_status', __('forum.flash.thread_duplicate'));

        // Outside the dedupe window, an identical resubmission is allowed
        // through as a deliberate repost.
        Carbon::setTestNow(now()->addSeconds(20));
        $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'Resubmitted Topic',
            'body' => 'Same opening post body.',
        ]);
        $this->assertSame(2, Thread::query()->where('title', 'Resubmitted Topic')->count());

        Carbon::setTestNow();
    }

    public function test_new_thread_updates_forum_stats_without_full_sync_recount(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $service = Mockery::mock(ForumStatsService::class);
        $service->shouldReceive('syncForForumId')->never();
        $service->shouldReceive('syncForForumIds')->never();
        $this->instance(ForumStatsService::class, $service);

        $this->actingAs($user)
            ->post(route('threads.create', $thread->forum), [
                'title' => 'Fast path topic',
                'body' => 'Fast path body.',
            ])
            ->assertRedirect();
    }

    public function test_new_thread_can_be_submitted_for_moderation_in_selected_forums(): void
    {
        [$thread] = $this->createPublicThread([], [
            'requires_thread_approval' => true,
        ]);
        $author = User::factory()->create();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($author)->post(route('threads.create', $thread->forum), [
            'title' => 'Needs Review',
            'body' => 'Please approve this thread.',
        ]);

        $createdThread = Thread::query()->where('title', 'Needs Review')->firstOrFail();
        $createdPost = Post::query()->where('thread_id', $createdThread->id)->firstOrFail();

        $response->assertRedirect(route('threads.show', $createdThread).'#post-'.$createdPost->id);
        $response->assertSessionHas('thread_status', __('forum.flash.thread_submitted'));
        $this->assertSame(ContentModerationStatus::PENDING, $createdThread->moderation_status);
        $this->assertSame(ContentModerationStatus::PENDING, $createdPost->moderation_status);

        $thread->forum->refresh();
        $this->assertSame(1, (int) $thread->forum->thread_count);
        $this->assertSame(1, (int) $thread->forum->post_count);

        $this->actingAs($author)
            ->get(route('threads.show', $createdThread))
            ->assertOk()
            ->assertSee('pending staff approval', false)
            ->assertSee('Please approve this thread.');

        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $createdThread))
            ->assertForbidden();

        $this->actingAs($moderator)->post(route('threads.moderate', $createdThread), [
            'action' => 'approve',
            'return_to' => route('threads.show', $createdThread),
        ])->assertRedirect(route('threads.show', $createdThread));

        $createdThread->refresh();
        $createdPost->refresh();
        $thread->forum->refresh();

        $this->assertSame(ContentModerationStatus::APPROVED, $createdThread->moderation_status);
        $this->assertSame(ContentModerationStatus::APPROVED, $createdPost->moderation_status);
        $this->assertSame(2, (int) $thread->forum->thread_count);
        $this->assertSame(2, (int) $thread->forum->post_count);

        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $createdThread))
            ->assertOk()
            ->assertSee('Please approve this thread.');
    }

    public function test_user_marked_for_first_post_moderation_has_new_thread_queued_in_unmoderated_forum(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'moderate_first_forum_post' => true,
        ]);

        $response = $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'First moderated thread',
            'body' => 'This should require approval.',
        ]);

        $createdThread = Thread::query()->where('title', 'First moderated thread')->firstOrFail();
        $createdPost = Post::query()->where('thread_id', $createdThread->id)->firstOrFail();

        $response->assertSessionHas('thread_status', __('forum.flash.thread_submitted'));
        $this->assertSame(ContentModerationStatus::PENDING, $createdThread->moderation_status);
        $this->assertSame(ContentModerationStatus::PENDING, $createdPost->moderation_status);
    }

    public function test_a_second_new_thread_is_blocked_while_the_first_moderated_thread_is_still_pending(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'moderate_first_forum_post' => true,
        ]);

        $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'First moderated thread',
            'body' => 'This should require approval.',
        ]);

        $response = $this->actingAs($user)->post(route('threads.create', $thread->forum), [
            'title' => 'Second thread while first is pending',
            'body' => 'This should be blocked entirely.',
        ]);

        $response
            ->assertRedirect(route('threads.create.form', $thread->forum))
            ->assertSessionHas('thread_status', __('forum.errors.awaiting_first_post_review'));

        $this->assertDatabaseMissing('threads', ['title' => 'Second thread while first is pending']);
    }

    public function test_unverified_user_is_redirected_to_verify_flow_for_new_thread_routes(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertSee('Verify email to start thread')
            ->assertDontSee('>Start New Thread<', false);

        $this->actingAs($user)
            ->get(route('threads.create.form', $thread->forum))
            ->assertRedirect(route('verification.notice', ['intended' => '/forums/'.$thread->forum_id.'/threads/new']));

        $this->actingAs($user)
            ->post(route('threads.create', $thread->forum), [
                'title' => 'Blocked title',
                'body' => 'Blocked body',
            ])
            ->assertRedirect(route('verification.notice', ['intended' => '/forums/'.$thread->forum_id.'/threads']));
    }

    public function test_authenticated_user_can_post_markdown_reply(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'signature' => "[i]Forum Signature[/i]\nRegards",
        ]);

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => "**Thanks**\n\n> **Admin:**\n> Hello",
        ]);

        $post = Post::query()->where('thread_id', $thread->id)->latest('id')->first();

        $response->assertRedirect(route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$post?->id);
        $this->assertNotNull($post);
        $this->assertSame("**Thanks**\n\n> **Admin:**\n> Hello", $post->body);
        $this->assertSame('markdown', $post->content_format);
        $this->assertSame("[i]Forum Signature[/i]\nRegards", $post->signature);

        $thread->refresh();
        $this->assertSame(1, (int) $thread->reply_count);
        $this->assertSame($user->id, $thread->last_post_author_id);
        $this->assertNotNull($thread->last_post_at);

        $thread->forum->refresh();
        $this->assertSame(1, (int) $thread->forum->thread_count);
        $this->assertSame(2, (int) $thread->forum->post_count);
        $this->assertSame((int) $post->id, (int) ($thread->forum->last_post_id ?? 0));
    }

    public function test_double_submitting_reply_does_not_create_duplicate(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        Carbon::setTestNow(now());

        $first = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Resubmitted reply body.',
        ]);
        $post = Post::query()->where('thread_id', $thread->id)->where('author_id', $user->id)->firstOrFail();
        $first->assertRedirect(route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$post->id);

        // Simulate a double-click/back-button resubmit a few seconds later.
        $second = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Resubmitted reply body.',
        ]);

        $this->assertSame(1, Post::query()->where('thread_id', $thread->id)->where('author_id', $user->id)->count());
        $second->assertRedirect(route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$post->id);
        $second->assertSessionHas('thread_status', __('forum.flash.reply_duplicate'));

        $thread->refresh();
        $this->assertSame(1, (int) $thread->reply_count);

        // Outside the dedupe window, an identical resubmission is allowed
        // through as a deliberate repost.
        Carbon::setTestNow(now()->addSeconds(20));
        $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Resubmitted reply body.',
        ]);
        $this->assertSame(2, Post::query()->where('thread_id', $thread->id)->where('author_id', $user->id)->count());

        Carbon::setTestNow();
    }

    public function test_thread_show_renders_line_breaks_and_styled_links_in_post_body(): void
    {
        [$thread, $author] = $this->createPublicThread();
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => "First line\nSecond line\n\n<https://example.com>",
            'content_format' => 'markdown',
        ]);

        $response = $this->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee("First line<br />\nSecond line", false);
        // Ampersand in Blade's `[&_a]` variant is HTML-escaped to `&amp;_a`
        // in the rendered class attribute, same as the existing
        // `[&_blockquote]`/`[&_code]` variants already used here — harmless,
        // browsers decode entities in attribute values before matching CSS
        // selectors — so assert on the unescaped portion instead.
        $response->assertSee('text-[color:var(--color-site-brand-dark)]', false);
        $response->assertSee('rel="nofollow noopener noreferrer" target="_blank" class="external-link" href="https://example.com"', false);
    }

    public function test_thread_show_keeps_same_site_links_in_the_current_tab(): void
    {
        [$thread, $author] = $this->createPublicThread();
        $siteUrl = rtrim((string) config('app.url'), '/');
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => "[another thread]({$siteUrl}/threads/other) and [a relative link](/threads/other)",
            'content_format' => 'markdown',
        ]);

        $response = $this->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('href="'.$siteUrl.'/threads/other">another thread', false);
        $response->assertSee('href="/threads/other">a relative link', false);
    }

    public function test_thread_show_links_a_mention_of_an_existing_user_to_their_member_page(): void
    {
        [$thread, $author] = $this->createPublicThread();
        $mentioned = User::factory()->create(['username' => 'wilt']);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Thanks @wilt for the fix.',
            'content_format' => 'markdown',
        ]);

        $response = $this->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('<a href="/members/'.$mentioned->id.'">@wilt</a>', false);
    }

    public function test_thread_show_leaves_a_mention_of_an_unknown_user_as_plain_text(): void
    {
        [$thread, $author] = $this->createPublicThread();
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Thanks @nobody-registered for the fix.',
            'content_format' => 'markdown',
        ]);

        $response = $this->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('@nobody-registered');
        $response->assertDontSee('>@nobody-registered</a>', false);
    }

    public function test_reply_syncs_forum_stats_once_via_controller_flow(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $service = Mockery::mock(ForumStatsService::class);
        $service->shouldReceive('syncForForumId')->never();
        $service->shouldReceive('syncForForumIds')->never();
        $this->instance(ForumStatsService::class, $service);

        $this->actingAs($user)
            ->post(route('threads.reply', $thread), [
                'body' => 'Single sync reply body',
            ])
            ->assertRedirect();
    }

    public function test_reply_does_not_subscribe_the_replier_by_default(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Reply that should not subscribe',
        ])->assertRedirect();

        $this->assertDatabaseMissing('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_reply_subscribes_the_replier_when_the_subscribe_box_is_ticked(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Reply that subscribes',
            'subscribe' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_reply_follows_the_account_setting_when_the_form_sends_no_choice_and_the_box_overrides_it(): void
    {
        [$thread] = $this->createPublicThread();
        $subscriber = User::factory()->create(['auto_subscribe_on_post' => true]);
        $optedOut = User::factory()->create(['auto_subscribe_on_post' => true]);

        $this->actingAs($subscriber)->post(route('threads.reply', $thread), ['body' => 'Falls back to my setting'])->assertRedirect();
        $this->actingAs($optedOut)->post(route('threads.reply', $thread), ['body' => 'Unticked on this one', 'subscribe' => '0'])->assertRedirect();

        $this->assertDatabaseHas('thread_subscriptions', ['thread_id' => $thread->id, 'user_id' => $subscriber->id]);
        $this->assertDatabaseMissing('thread_subscriptions', ['thread_id' => $thread->id, 'user_id' => $optedOut->id]);
    }

    public function test_reply_form_subscribe_box_starts_from_the_account_setting_and_is_hidden_when_already_subscribed(): void
    {
        [$thread] = $this->createPublicThread();
        $off = User::factory()->create();
        $on = User::factory()->create(['auto_subscribe_on_post' => true]);

        $offHtml = $this->actingAs($off)->get(route('threads.show', $thread))->getContent();
        $onHtml = $this->actingAs($on)->get(route('threads.show', $thread))->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="subscribe"\s+value="1"\s+checked/', $offHtml);
        $this->assertMatchesRegularExpression('/name="subscribe"\s+value="1"\s+checked/', $onHtml);

        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $on->id]);
        $this->actingAs($on)->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Subscribe to this thread');
    }

    public function test_reply_can_be_submitted_for_moderation_without_notifying_subscribers(): void
    {
        Notification::fake();

        [$thread] = $this->createPublicThread([], [
            'requires_reply_approval' => true,
        ]);
        $author = User::factory()->create();
        $subscriber = User::factory()->create();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $subscriber->id,
        ]);

        $response = $this->actingAs($author)->post(route('threads.reply', $thread), [
            'body' => 'Pending reply body',
        ]);

        $post = Post::query()->where('thread_id', $thread->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$post->id);
        $response->assertSessionHas('thread_status', __('forum.flash.reply_submitted'));
        $this->assertSame(ContentModerationStatus::PENDING, $post->moderation_status);

        $thread->refresh();
        $thread->forum->refresh();
        $this->assertSame(0, (int) $thread->reply_count);
        $this->assertSame(1, (int) $thread->forum->post_count);
        Notification::assertNothingSent();

        $this->actingAs($author)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Pending reply body')
            ->assertSee('pending staff approval', false);

        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Pending reply body');

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'approve',
            'return_to' => route('threads.show', $thread),
        ])->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $thread->refresh();
        $thread->forum->refresh();
        $post->refresh();

        $this->assertSame(ContentModerationStatus::APPROVED, $post->moderation_status);
        $this->assertSame(1, (int) $thread->reply_count);
        $this->assertSame(2, (int) $thread->forum->post_count);

        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Pending reply body');
    }

    public function test_user_marked_for_always_moderation_has_replies_queued_in_unmoderated_forum(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'always_moderate_forum_content' => true,
        ]);

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Always moderated reply',
        ]);

        $post = Post::query()->where('thread_id', $thread->id)->latest('id')->firstOrFail();

        $response->assertSessionHas('thread_status', __('forum.flash.reply_submitted'));
        $this->assertSame(ContentModerationStatus::PENDING, $post->moderation_status);
    }

    public function test_thread_view_renders_the_authors_current_signature_not_a_snapshot(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'signature' => '[b]Support Team[/b]',
        ]);

        $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Reply with signature',
        ])->assertRedirect();

        $user->update(['signature' => '[b]Changed Signature[/b]']);

        $response = $this->actingAs($user)->get(route('threads.show', $thread));
        $response->assertOk();
        $response->assertSee('Changed Signature');
        $response->assertDontSee('Support Team');
    }

    public function test_thread_view_renders_legacy_quoted_color_markdown_in_signature_snapshot(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'signature' => '[COLOR=\"red\"]Support Team[/COLOR]',
        ]);

        $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Reply with colored signature',
        ])->assertRedirect();

        $response = $this->actingAs($user)->get(route('threads.show', $thread));
        $response->assertOk();
        $response->assertSee('<span style="color:red;">Support Team</span>', false);
    }

    public function test_thread_view_renders_markdown_bodies_and_signatures(): void
    {
        [$thread] = $this->createPublicThread();
        $firstPost = Post::query()->where('thread_id', $thread->id)->oldest('id')->firstOrFail();
        $firstPost->update([
            'body' => '**First body**',
            'content_format' => 'markdown',
        ]);
        $user = User::factory()->create([
            'signature' => '**Markdown signature** [color=red]Team[/color]',
            'signature_format' => 'markdown',
        ]);

        $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Markdown editor body',
        ])->assertRedirect();

        $markdownPost = Post::query()->where('thread_id', $thread->id)->latest('id')->firstOrFail();
        $this->assertSame('markdown', $markdownPost->signature_format);
        $markdownPost->update([
            'body' => '**Markdown body** <script>alert(1)</script>',
            'content_format' => 'markdown',
        ]);

        $response = $this->actingAs($user)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertSee('<strong>First body</strong>', false);
        $response->assertSee('<strong>Markdown body</strong>', false);
        $response->assertSee('<strong>Markdown signature</strong> <span style="color:red;">Team</span>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_thread_view_shows_reply_moderation_cta_when_forum_requires_reply_approval(): void
    {
        [$thread] = $this->createPublicThread([], [
            'requires_reply_approval' => true,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Replies are moderated in this thread.')
            ->assertSee('Submit Reply for Approval');
    }

    public function test_thread_view_shows_reply_moderation_cta_for_user_first_post_flag_in_unmoderated_forum(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'moderate_first_forum_post' => true,
        ]);

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Replies are moderated in this thread.')
            ->assertSee('Submit Reply for Approval');
    }

    public function test_user_marked_for_first_post_moderation_only_has_first_forum_contribution_queued(): void
    {
        [$thread] = $this->createPublicThread();
        $user = User::factory()->create([
            'moderate_first_forum_post' => true,
        ]);

        $firstResponse = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'My first moderated reply',
        ]);

        $firstPost = Post::query()->where('thread_id', $thread->id)->latest('id')->firstOrFail();

        $firstResponse->assertSessionHas('thread_status', __('forum.flash.reply_submitted'));
        $this->assertSame(ContentModerationStatus::PENDING, $firstPost->moderation_status);

        // A second submission while the first is still unreviewed must be
        // blocked outright, not silently approved (the pre-fix behaviour
        // here treated the pending first post's mere existence as proof the
        // user had "posted before", skipping moderation for every reply
        // after the first) and not queued either (accepting it would let a
        // flagged user build an unbounded backlog of unreviewed content
        // before a moderator looks at any of it).
        $secondResponse = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'My second reply',
        ]);

        $secondResponse
            ->assertRedirect(route('threads.show', $thread).'#reply')
            ->assertSessionHasErrors(['body' => __('forum.errors.awaiting_first_post_review')], null, 'reply');

        $thread->refresh();
        $thread->forum->refresh();
        $this->assertSame(0, (int) $thread->reply_count);
        $this->assertSame(1, (int) $thread->forum->post_count);
        $this->assertSame(
            1,
            Post::query()->where('thread_id', $thread->id)->where('author_id', $user->id)->count()
        );

        // Once the first post is approved, replying works normally again.
        $firstPost->forceFill(['moderation_status' => ContentModerationStatus::APPROVED])->save();

        $thirdResponse = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'My reply after approval',
        ]);

        $thirdPost = Post::query()->where('thread_id', $thread->id)->where('author_id', $user->id)->latest('id')->firstOrFail();

        $thirdResponse->assertRedirect(route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$thirdPost->id);
        $this->assertSame(ContentModerationStatus::APPROVED, $thirdPost->moderation_status);
    }

    public function test_guest_reply_redirects_to_login_modal_flow(): void
    {
        [$thread] = $this->createPublicThread();

        $response = $this->post(route('threads.reply', $thread), [
            'body' => 'Test reply',
        ]);

        $response->assertRedirect(route('home', [
            'auth' => 'login',
            'intended' => route('threads.show', $thread),
        ]));
    }

    public function test_reply_sends_email_notification_to_subscribed_users_except_replier(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        Notification::fake();

        [$thread] = $this->createPublicThread();
        $replier = User::factory()->create();
        $subscriber = User::factory()->create();

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $subscriber->id,
        ]);

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $replier->id,
        ]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), [
            'body' => 'New reply for subscribers',
        ]);

        Notification::assertSentTo($subscriber, ThreadUpdatedNotification::class);
        Notification::assertNotSentTo($replier, ThreadUpdatedNotification::class);
        $this->assertDatabaseCount('thread_subscription_digest_items', 0);
    }

    public function test_reply_does_not_email_subscriber_when_thread_email_is_disabled(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        Notification::fake();

        [$thread] = $this->createPublicThread();
        $replier = User::factory()->create();
        $subscriber = User::factory()->create();

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $subscriber->id,
        ]);

        UserNotificationPreference::query()->create([
            'user_id' => $subscriber->id,
            'preference_key' => 'forum.thread_updates_instant',
            'value' => '0',
            'source' => 'user',
            'updated_by_user_id' => $subscriber->id,
        ]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), [
            'body' => 'New reply with email disabled',
        ]);

        Notification::assertSentTo($subscriber, ThreadUpdatedNotification::class, fn ($n, array $channels) => ! in_array('mail', $channels, true));
        $this->assertDatabaseCount('thread_subscription_digest_items', 0);
    }

    public function test_reply_does_not_email_subscriber_who_is_suppressed(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        Notification::fake();

        [$thread] = $this->createPublicThread();
        $replier = User::factory()->create();
        $subscriber = User::factory()->create([
            'receives_email' => false,
        ]);

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $subscriber->id,
        ]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), [
            'body' => 'New reply for a suppressed subscriber',
        ]);

        Notification::assertSentTo($subscriber, ThreadUpdatedNotification::class, fn ($n, array $channels) => ! in_array('mail', $channels, true));
    }

    public function test_thread_update_notification_includes_manage_subscriptions_link(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        [$thread] = $this->createPublicThread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $subscriber = User::factory()->create();

        $notification = new ThreadUpdatedNotification($thread, $post);
        $mail = $notification->toMail($subscriber);

        $this->assertSame('emails.notifications.thread-updated', $mail->markdown);
        $this->assertSame(__('notifications.thread.subjects.updated', ['thread' => $thread->title]), $mail->subject);
        $this->assertSame(route('profile.show', ['tab' => 'subscriptions']), $mail->viewData['manageSubscriptionsUrl'] ?? null);
        $this->assertSame(route('profile.show', ['tab' => 'settings']), $mail->viewData['managePreferencesUrl'] ?? null);
        $this->assertStringContainsString('/email-preferences/unsubscribe/', (string) ($mail->viewData['unsubscribeUrl'] ?? ''));
        $this->assertStringContainsString('signature=', (string) ($mail->viewData['unsubscribeUrl'] ?? ''));
        $this->assertStringContainsString('/email-preferences/unsubscribe/', (string) ($mail->viewData['oneClickUnsubscribeUrl'] ?? ''));
        $this->assertStringContainsString('/one-click?', (string) ($mail->viewData['oneClickUnsubscribeUrl'] ?? ''));
        $this->assertStringContainsString('signature=', (string) ($mail->viewData['oneClickUnsubscribeUrl'] ?? ''));
    }

    public function test_thread_update_notification_links_to_page_one_when_the_post_is_on_the_first_page(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        [$thread] = $this->createPublicThread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $subscriber = User::factory()->create();

        $mail = (new ThreadUpdatedNotification($thread, $post))->toMail($subscriber);

        $this->assertSame(
            route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$post->id,
            $mail->viewData['threadUrl'] ?? null,
        );
    }

    public function test_thread_update_notification_deep_links_to_the_page_a_later_post_falls_on(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        [$thread, $author] = $this->createPublicThread();
        // createPublicThread already created post #1; add enough replies that
        // the 25th post lands on page 2 (THREAD_POSTS_PER_PAGE is 20), then
        // target that post specifically.
        for ($i = 2; $i <= 25; $i++) {
            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $author->id,
                'body' => "Reply {$i}",
            ]);
        }
        $targetPost = Post::query()->where('thread_id', $thread->id)->orderBy('id')->skip(24)->firstOrFail();
        $subscriber = User::factory()->create();

        $mail = (new ThreadUpdatedNotification($thread, $targetPost))->toMail($subscriber);

        $this->assertSame(
            route('threads.show', ['thread' => $thread, 'page' => 2]).'#post-'.$targetPost->id,
            $mail->viewData['threadUrl'] ?? null,
        );
    }

    public function test_thread_update_notification_uses_plain_text_markdown_excerpt(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        [$thread] = $this->createPublicThread();
        $subscriber = User::factory()->create();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $post->update([
            'body' => '**Important** [documentation](https://example.com) [color=red]update[/color]',
            'content_format' => 'markdown',
        ]);

        $mail = (new ThreadUpdatedNotification($thread, $post->fresh()))->toMail($subscriber);

        $this->assertSame('Important documentation update', $mail->viewData['excerpt'] ?? null);
    }

    public function test_reply_queues_digest_item_for_daily_subscribers(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        Notification::fake();

        [$thread] = $this->createPublicThread();
        $replier = User::factory()->create();
        $dailySubscriber = User::factory()->create([
            'thread_subscription_email_frequency' => 'daily',
        ]);

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $dailySubscriber->id,
        ]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), [
            'body' => 'Daily digest reply',
        ]);

        Notification::assertSentTo($dailySubscriber, ThreadUpdatedNotification::class, fn ($n, array $channels) => $channels === [CoalescingDatabaseChannel::class]);
        $this->assertSame(1, ThreadSubscriptionDigestItem::query()->where('user_id', $dailySubscriber->id)->count());
    }

    public function test_reply_does_not_push_to_a_digest_subscriber_even_with_a_registered_browser(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        Notification::fake();

        [$thread] = $this->createPublicThread();
        $replier = User::factory()->create();
        $dailySubscriber = User::factory()->create([
            'thread_subscription_email_frequency' => 'daily',
        ]);
        $dailySubscriber->updatePushSubscription('https://fcm.googleapis.com/fcm/send/digest-user', 'p', 'a');

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $dailySubscriber->id,
        ]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), [
            'body' => 'Daily digest reply',
        ]);

        // Digest = less noise: the interrupting push is suppressed, only the
        // (pull) bell row is written.
        Notification::assertSentTo(
            $dailySubscriber,
            ThreadUpdatedNotification::class,
            fn ($n, array $channels) => $channels === [CoalescingDatabaseChannel::class],
        );
    }

    public function test_reply_does_not_queue_digest_item_when_digest_email_is_disabled(): void
    {
        $this->markTestSkipped('Subscriber notifications moved into the forums, notifications and push packages and are tested there; the in-app bell arrives with slice S6.');

        Notification::fake();

        [$thread] = $this->createPublicThread();
        $replier = User::factory()->create();
        $dailySubscriber = User::factory()->create([
            'thread_subscription_email_frequency' => 'daily',
        ]);

        ThreadSubscription::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $dailySubscriber->id,
        ]);

        UserNotificationPreference::query()->create([
            'user_id' => $dailySubscriber->id,
            'preference_key' => 'forum.thread_updates_digest',
            'value' => '0',
            'source' => 'user',
            'updated_by_user_id' => $dailySubscriber->id,
        ]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), [
            'body' => 'Daily digest disabled reply',
        ]);

        Notification::assertSentTo($dailySubscriber, ThreadUpdatedNotification::class, fn ($n, array $channels) => $channels === [CoalescingDatabaseChannel::class]);
        $this->assertSame(0, ThreadSubscriptionDigestItem::query()->where('user_id', $dailySubscriber->id)->count());
    }

    public function test_locked_thread_rejects_reply_and_does_not_create_post(): void
    {
        [$thread] = $this->createPublicThread(['is_locked' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Attempt on locked thread',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#reply');
        $response->assertSessionHasErrors(['body'], null, 'reply');
        $this->assertSame(1, Post::query()->where('thread_id', $thread->id)->count());
    }

    public function test_forum_posting_policy_can_block_reply_for_regular_user(): void
    {
        [$thread] = $this->createPublicThread([], ['posting_policy' => ForumPostingPolicy::STAFF_ONLY]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Attempt with blocked posting policy',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#reply');
        $response->assertSessionHasErrors(['body'], null, 'reply');
        $this->assertSame(1, Post::query()->where('thread_id', $thread->id)->count());
    }

    public function test_thread_author_can_reply_without_reply_entitlement(): void
    {
        [$thread, $author] = $this->createPublicThread([], [
            'reply_entitlement_scope' => 'forums.general_support.reply',
        ]);

        $response = $this->actingAs($author)->post(route('threads.reply', $thread), [
            'body' => 'Author follow-up reply',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Author follow-up reply',
        ]);
    }

    public function test_non_author_cannot_reply_without_required_reply_entitlement(): void
    {
        [$thread] = $this->createPublicThread([], [
            'reply_entitlement_scope' => 'forums.general_support.reply',
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Blocked reply',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#reply');
        $response->assertSessionHasErrors(['body'], null, 'reply');
        $this->assertDatabaseMissing('posts', [
            'thread_id' => $thread->id,
            'author_id' => $user->id,
            'body' => 'Blocked reply',
        ]);
    }

    public function test_non_author_with_required_reply_entitlement_can_reply(): void
    {
        [$thread] = $this->createPublicThread([], [
            'reply_entitlement_scope' => 'forums.general_support.reply',
        ]);
        $user = User::factory()->create();
        $plan = MembershipPlan::query()->create([
            'code' => 'general-support-replies',
            'name' => 'General Support Replies',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
            'entitlements' => ['forums.general_support.reply'],
        ]);

        UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'stripe',
            'subscription_name' => 'membership_general_support_replies',
            'provider_subscription_id' => 'sub_general_support_replies',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => ['forums.general_support.reply'],
        ]);

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Entitled reply',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'thread_id' => $thread->id,
            'author_id' => $user->id,
            'body' => 'Entitled reply',
        ]);
    }

    public function test_flagged_empty_staff_author_only_forum_allows_first_thread_creation(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Security',
            'slug' => 'security',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Reports of Security Problems',
            'slug' => 'reports-of-security-problems-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::STAFF_AUTHOR_ONLY,
            'posting_policy' => ForumPostingPolicy::STAFF_AUTHOR_ONLY,
            'is_read_only' => false,
            'show_when_empty' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Start New Thread');

        $response = $this->actingAs($user)->post(route('threads.create', $forum), [
            'title' => 'First Security Report',
            'body' => 'Initial vulnerability report details.',
        ]);

        $thread = Thread::query()->where('forum_id', $forum->id)->where('title', 'First Security Report')->first();
        $this->assertNotNull($thread);
        $this->assertSame($user->id, $thread->author_id);

        $post = Post::query()->where('thread_id', $thread->id)->first();
        $this->assertNotNull($post);
        $this->assertSame('Initial vulnerability report details.', $post->body);

        $response->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
    }

    public function test_staff_author_only_forum_limits_non_staff_to_own_threads_and_replies(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Security',
            'slug' => 'security',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Reports of Security Problems',
            'slug' => 'reports-of-security-problems-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::STAFF_AUTHOR_ONLY,
            'posting_policy' => ForumPostingPolicy::STAFF_AUTHOR_ONLY,
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $owner = User::factory()->create();
        $otherAuthor = User::factory()->create();

        $ownerThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $owner->id,
            'title' => 'Owner Thread',
            'slug' => 'owner-thread-'.Str::lower(Str::random(6)),
        ]);
        Post::query()->create([
            'thread_id' => $ownerThread->id,
            'author_id' => $owner->id,
            'body' => 'Owner opening post',
        ]);

        $otherThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Other Thread',
            'slug' => 'other-thread-'.Str::lower(Str::random(6)),
        ]);
        Post::query()->create([
            'thread_id' => $otherThread->id,
            'author_id' => $otherAuthor->id,
            'body' => 'Other opening post',
        ]);

        $this->actingAs($owner)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Owner Thread')
            ->assertDontSee('Other Thread');

        $this->actingAs($owner)
            ->get(route('threads.show', $otherThread))
            ->assertForbidden();

        $beforeCount = Post::query()->where('thread_id', $otherThread->id)->count();
        $this->actingAs($owner)
            ->post(route('threads.reply', $otherThread), [
                'body' => 'Not allowed reply',
            ])
            ->assertForbidden();
        $this->assertSame($beforeCount, Post::query()->where('thread_id', $otherThread->id)->count());
    }

    public function test_paid_or_author_forum_limits_non_members_to_their_own_direct_thread_view(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Commercial Help Wanted',
            'slug' => 'commercial-help-wanted-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PAID_OR_AUTHOR,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'required_entitlement_scope' => 'forums.commercial_help_wanted.access',
            'reply_entitlement_scope' => 'forums.commercial_help_wanted.access',
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $owner = User::factory()->create();
        $otherAuthor = User::factory()->create();

        $ownerThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $owner->id,
            'title' => 'Owner Commercial Thread',
            'slug' => 'owner-commercial-thread-'.Str::lower(Str::random(6)),
        ]);
        Post::query()->create([
            'thread_id' => $ownerThread->id,
            'author_id' => $owner->id,
            'body' => 'Owner commercial opening post',
        ]);

        $otherThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Other Commercial Thread',
            'slug' => 'other-commercial-thread-'.Str::lower(Str::random(6)),
        ]);
        Post::query()->create([
            'thread_id' => $otherThread->id,
            'author_id' => $otherAuthor->id,
            'body' => 'Other commercial opening post',
        ]);

        $this->actingAs($owner)
            ->get(route('threads.show', $ownerThread))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('threads.show', $otherThread))
            ->assertForbidden();

        $plan = MembershipPlan::query()->create([
            'code' => 'commercial-help-provider',
            'name' => 'Commercial Help Provider',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
            'entitlements' => ['forums.commercial_help_wanted.access'],
        ]);
        $provider = User::factory()->create();
        UserMembership::query()->create([
            'user_id' => $provider->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'stripe',
            'subscription_name' => 'commercial_help_provider',
            'provider_subscription_id' => 'sub_commercial_help_provider',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => ['forums.commercial_help_wanted.access'],
        ]);

        $this->actingAs($provider)
            ->get(route('threads.show', $otherThread))
            ->assertOk();

        $this->actingAs($provider)
            ->post(route('threads.reply', $otherThread), [
                'body' => 'Provider response',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'thread_id' => $otherThread->id,
            'author_id' => $provider->id,
            'body' => 'Provider response',
        ]);
    }

    /**
     * @return array{0: Thread, 1: User}
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

        $author = $threadOverrides['author'] ?? null;
        if (! $author instanceof User) {
            $author = User::factory()->create();
        }

        unset($threadOverrides['author']);

        $thread = Thread::query()->create(array_merge([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome Thread',
            'slug' => 'welcome-thread-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
        ], $threadOverrides));

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        return [$thread, $author];
    }
}
