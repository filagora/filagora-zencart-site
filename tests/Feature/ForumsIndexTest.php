<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffFrontendPreview;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Enums\PostReportStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\PostReport;
use Filagora\Memberships\Enums\MembershipStatus;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ForumsIndexTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_guest_only_sees_public_forums(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $publicForum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $this->createForum($group, 'Registered Forum', ForumVisibility::REGISTERED);

        $response = $this->get('/forums');

        $response->assertOk();
        $response->assertSee($publicForum->name);
        $response->assertDontSee('Registered Forum');
    }

    public function test_forum_index_strips_legacy_inline_styles_from_descriptions(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Security',
            'slug' => 'security-legacy-style',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Concerns about Hack Attempts', ForumVisibility::PUBLIC);
        $forum->update([
            'description' => 'Follow this article: <a href="https://docs.zen-cart.com/user/security/hacked/" class="linkAttn" target="_blank" style="white-space: nowrap; font-size: x-large;">CLICK HERE - Recovering from Hacks.</a>',
        ]);

        $response = $this->get('/forums');

        $response->assertOk();
        $response->assertDontSee('white-space: nowrap', false);
        $response->assertDontSee('font-size: x-large', false);
        $response->assertDontSee('class="linkAttn"', false);
        $response->assertSee('href="https://docs.zen-cart.com/user/security/hacked/"', false);
        $response->assertSee('CLICK HERE - Recovering from Hacks.');
    }

    public function test_registered_user_can_enter_paid_or_author_forum_and_create_a_private_thread(): void
    {
        $this->markTestSkipped('Needs thread creation and moderation: slice S3 (forum writing).');

        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial-private-threads',
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Commercial Help Wanted',
            'slug' => 'commercial-help-wanted-private-threads',
            'visibility' => ForumVisibility::PAID_OR_AUTHOR,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'required_entitlement_scope' => 'forums.commercial_help_wanted.access',
            'reply_entitlement_scope' => 'forums.commercial_help_wanted.access',
            'is_read_only' => false,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherAuthor = User::factory()->create();
        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Another customer request',
            'slug' => 'another-customer-request',
        ]);

        $this->actingAs($user)
            ->get('/forums')
            ->assertOk()
            ->assertSee('Commercial Help Wanted');

        $this->actingAs($user)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertDontSee('Another customer request')
            ->assertSee('Start New Thread');

        $this->actingAs($user)
            ->post(route('threads.create', $forum), [
                'title' => 'My commercial support request',
                'body' => 'I need help with a commercial project.',
            ])
            ->assertRedirect();

        $thread = Thread::query()->where('title', 'My commercial support request')->firstOrFail();
        $this->assertSame($user->id, $thread->author_id);

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('I need help with a commercial project.');
    }

    public function test_user_with_direct_entitlement_grant_sees_paid_or_author_forum_threads_without_membership(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial-direct-grant',
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Commercial Help Wanted',
            'slug' => 'commercial-help-wanted-direct-grant',
            'visibility' => ForumVisibility::PAID_OR_AUTHOR,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'required_entitlement_scope' => 'forums.commercial_help_wanted.access',
            'reply_entitlement_scope' => 'forums.commercial_help_wanted.access',
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['membership_expires_at' => null]);
        $otherAuthor = User::factory()->create();
        $otherThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Another customer request',
            'slug' => 'another-customer-request-direct-grant',
        ]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'forums.commercial_help_wanted.access',
            'grant_type' => 'manual',
            'granted_by_id' => $user->id,
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee($otherThread->title);
    }

    public function test_guest_intro_auth_controls_use_real_links_with_intended_forum_url(): void
    {
        $response = $this->get('/forums');
        $registerUrl = e(route('home', ['auth' => 'register', 'intended' => '/forums']));
        $loginUrl = e(route('home', ['auth' => 'login', 'intended' => '/forums']));

        $response->assertOk();
        $response->assertSee('href="'.$registerUrl.'"', false);
        $response->assertSee('href="'.$loginUrl.'"', false);
        $response->assertSee('data-auth-modal-url="/register"', false);
        $response->assertSee('data-auth-modal-url="/login"', false);
    }

    public function test_authenticated_user_sees_registered_but_not_paid_forum_without_membership(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $registeredForum = $this->createForum($group, 'Registered Forum', ForumVisibility::REGISTERED);
        $this->createForum($group, 'Paid Forum', ForumVisibility::PAID_ONLY);

        $response = $this->actingAs(User::factory()->create())->get('/forums');

        $response->assertOk();
        $response->assertSee($registeredForum->name);
        $response->assertDontSee('Paid Forum');
    }

    public function test_member_user_sees_paid_forum(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $paidForum = $this->createForum($group, 'Paid Forum', ForumVisibility::PAID_ONLY);
        $member = User::factory()->create([
            'membership_expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($member)->get('/forums');

        $response->assertOk();
        $response->assertSee($paidForum->name);
    }

    public function test_member_user_sees_paid_forum_via_active_membership_record(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-membership-record',
            'is_active' => true,
        ]);

        $paidForum = $this->createForum($group, 'Paid Forum', ForumVisibility::PAID_ONLY);
        $plan = MembershipPlan::query()->create([
            'code' => 'community-plus',
            'name' => 'Community Plus',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
        ]);
        $member = User::factory()->create([
            'membership_expires_at' => null,
        ]);

        UserMembership::query()->create([
            'user_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'stripe',
            'subscription_name' => 'default',
            'provider_subscription_id' => 'sub_membership_record',
            'starts_at' => now()->subDay(),
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => [],
        ]);

        $response = $this->actingAs($member)->get('/forums');

        $response->assertOk();
        $response->assertSee($paidForum->name);
    }

    public function test_user_with_direct_entitlement_sees_entitlement_only_forum_without_membership(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Restricted',
            'slug' => 'restricted',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'New Website Feedback',
            'slug' => 'new-website-feedback',
            'visibility' => ForumVisibility::ENTITLEMENT_ONLY,
            'required_entitlement_scope' => 'forums.new_website_feedback.view',
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'membership_expires_at' => null,
        ]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'forums.new_website_feedback.view',
            'grant_type' => 'manual',
            'granted_by_id' => $user->id,
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->get('/forums')
            ->assertOk()
            ->assertSee($forum->name);
    }

    public function test_user_without_entitlement_does_not_see_entitlement_only_forum(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Restricted',
            'slug' => 'restricted-hidden',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'New Website Feedback',
            'slug' => 'new-website-feedback-hidden',
            'visibility' => ForumVisibility::ENTITLEMENT_ONLY,
            'required_entitlement_scope' => 'forums.new_website_feedback.view',
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/forums')
            ->assertOk()
            ->assertDontSee($forum->name);
    }

    public function test_today_listing_respects_forum_entitlements_for_paid_or_author_forums(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Commercial Help Wanted',
            'slug' => 'commercial-help-wanted',
            'visibility' => ForumVisibility::PAID_OR_AUTHOR,
            'required_entitlement_scope' => 'forums.commercial_help_wanted.view',
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'code' => 'community-plus',
            'name' => 'Community Plus',
            'is_active' => true,
            'is_free' => false,
            'provider' => 'stripe',
        ]);

        $user = User::factory()->create();
        $otherAuthor = User::factory()->create();

        UserMembership::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => MembershipStatus::ACTIVE->value,
            'provider' => 'stripe',
            'subscription_name' => 'default',
            'provider_subscription_id' => 'sub_entitlement_filter',
            'starts_at' => now()->subDay(),
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'granted_entitlements_snapshot' => [],
        ]);

        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'My Commercial Thread',
            'slug' => 'my-commercial-thread',
            'created_at' => now(),
            'last_post_at' => now(),
        ]);

        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Other Commercial Thread',
            'slug' => 'other-commercial-thread',
            'created_at' => now(),
            'last_post_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('forums.today'))
            ->assertOk()
            ->assertSee('My Commercial Thread')
            ->assertDontSee('Other Commercial Thread');
    }

    public function test_staff_author_only_forum_is_visible_to_thread_author(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $authorOnlyForum = $this->createForum($group, 'Author Only Forum', ForumVisibility::STAFF_AUTHOR_ONLY);
        $author = User::factory()->create();

        Thread::query()->create([
            'forum_id' => $authorOnlyForum->id,
            'author_id' => $author->id,
            'title' => 'Author Thread',
            'slug' => 'author-thread',
        ]);

        $response = $this->actingAs($author)->get('/forums');

        $response->assertOk();
        $response->assertSee($authorOnlyForum->name);
    }

    public function test_staff_author_only_forum_can_be_visible_when_empty_if_flagged(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Security',
            'slug' => 'security',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Reports of Security Problems',
            'slug' => 'reports-of-security-problems',
            'visibility' => ForumVisibility::STAFF_AUTHOR_ONLY,
            'posting_policy' => ForumPostingPolicy::STAFF_AUTHOR_ONLY,
            'is_read_only' => false,
            'show_when_empty' => true,
            'is_active' => true,
        ]);

        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->get('/forums')
            ->assertOk()
            ->assertSee($forum->name);

        $this->actingAs($regularUser)
            ->get(route('forums.show', $forum))
            ->assertOk();
    }

    public function test_staff_only_forum_is_hidden_from_non_staff_users(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $staffOnlyForum = $this->createForum($group, 'Staff Forum', ForumVisibility::STAFF_ONLY);
        $regularUser = User::factory()->create();

        $this->get('/forums')
            ->assertOk()
            ->assertDontSee($staffOnlyForum->name);

        $this->actingAs($regularUser)
            ->get('/forums')
            ->assertOk()
            ->assertDontSee($staffOnlyForum->name);

        $this->actingAs($regularUser)
            ->get(route('forums.show', $staffOnlyForum))
            ->assertForbidden();
    }

    public function test_staff_only_forum_is_visible_to_forum_moderator(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $staffOnlyForum = $this->createForum($group, 'Staff Forum', ForumVisibility::STAFF_ONLY);
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get('/forums')
            ->assertOk()
            ->assertSee($staffOnlyForum->name);

        $this->actingAs($moderator)
            ->get(route('forums.show', $staffOnlyForum))
            ->assertOk();
    }

    public function test_staff_preview_mode_hides_staff_only_forum_from_forum_moderator(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-preview',
            'is_active' => true,
        ]);

        $staffOnlyForum = $this->createForum($group, 'Staff Forum', ForumVisibility::STAFF_ONLY);
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get('/forums')
            ->assertOk()
            ->assertDontSee($staffOnlyForum->name);

        $this->actingAs($moderator)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('forums.show', $staffOnlyForum))
            ->assertForbidden();
    }

    public function test_today_and_unread_activity_pages_link_back_to_the_forums_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forums.today'))
            ->assertOk()
            ->assertSee('href="'.route('forums.index').'"', false);

        $this->actingAs($user)
            ->get(route('forums.unread'))
            ->assertOk()
            ->assertSee('href="'.route('forums.index').'"', false);
    }

    public function test_today_activity_page_shows_correct_post_count_and_links_to_latest_post(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-today-counts',
            'is_active' => true,
        ]);
        $forum = $this->createForum($group, 'Today Counts Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $viewer = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread With Replies',
            'slug' => 'thread-with-replies',
            'created_at' => now(),
            'last_post_at' => now(),
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);
        $latestPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Second post',
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);

        $response = $this->actingAs($viewer)->get(route('forums.today'));

        $response->assertOk();
        $response->assertSee('Posts: 2', false);
        $response->assertSee('Replies: 1', false);
        $response->assertSee(
            'href="'.route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$latestPost->id.'"',
            false
        );
    }

    public function test_forums_index_shows_forum_moderation_queue_for_staff_only(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-staff-queue',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Queued Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Pending Queue Thread',
            'slug' => 'pending-queue-thread',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Pending starter',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $reply = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Rejected reply body',
            'moderation_status' => ContentModerationStatus::REJECTED,
            'moderation_reason' => 'Needs revision',
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get('/forums')
            ->assertOk()
            ->assertSee('Forum Moderation Queue')
            ->assertSee('Pending Queue Thread')
            ->assertSee('Rejected reply body')
            ->assertSee(route('threads.moderate', $thread))
            ->assertSee(route('posts.moderate', $reply));

        $this->actingAs(User::factory()->create())
            ->get('/forums')
            ->assertOk()
            ->assertDontSee('Forum Moderation Queue')
            ->assertDontSee('Rejected reply body');
    }

    public function test_forums_index_moderation_queue_links_pending_post_to_its_actual_page(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-queue-deeplink',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Deep Link Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Deep Link Thread',
            'slug' => 'deep-link-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        for ($i = 0; $i < 25; $i++) {
            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $author->id,
                'body' => 'Approved post '.$i,
                'moderation_status' => ContentModerationStatus::APPROVED,
            ])->forceFill(['created_at' => now()->addSeconds($i)])->saveQuietly();
        }

        $pendingReply = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Pending reply needing review',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);
        $pendingReply->forceFill(['created_at' => now()->addSeconds(25)])->saveQuietly();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->get('/forums')->assertOk();

        $response->assertSee(
            'href="'.e(route('threads.show', ['thread' => $thread, 'page' => 2]).'#post-'.$pendingReply->id).'"',
            false,
        );
        $response->assertDontSee(
            'href="'.e(route('threads.show', $thread).'#post-'.$pendingReply->id).'"',
            false,
        );
    }

    public function test_forums_index_moderation_queue_lists_reported_posts(): void
    {
        $this->markTestSkipped('Needs thread creation and moderation: slice S3 (forum writing).');

        $group = ForumGroup::query()->create([
            'name' => 'General', 'slug' => 'general-reported-index-queue', 'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id, 'name' => 'Reported Index Forum',
            'slug' => 'reported-index-forum', 'is_active' => true,
        ]);
        $author = User::factory()->create(['username' => 'ad_bot']);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'Reported Index Thread',
            'slug' => 'reported-index-thread', 'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        Post::query()->create([
            'thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'opening',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        $reply = Post::query()->create([
            'thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'buy cheap watches here',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        $report = PostReport::query()->create([
            'post_id' => $reply->id, 'thread_id' => $thread->id,
            'reporter_id' => User::factory()->create()->id, 'reason' => 'blatant spam',
            'status' => PostReportStatus::OPEN->value,
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get('/forums')
            ->assertOk()
            ->assertSee('Reported Posts')
            ->assertSee('buy cheap watches here')
            ->assertSee('blatant spam')
            ->assertSee('name="outcome" value="keep"', false)
            ->assertSee('name="outcome" value="remove"', false)
            ->assertSee('name="ban"', false)
            ->assertSee('name="ban_duration"', false)
            ->assertSee(route('moderation.reports.resolve', $report), false);

        // Full resolve from the front-end queue: remove the reply and ban the author for 7 days.
        $this->actingAs($moderator)
            ->post(route('moderation.reports.resolve', $report), [
                'outcome' => 'remove',
                'note' => 'spam',
                'ban' => '1',
                'ban_reason' => 'spam account',
                'ban_duration' => '7d',
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $reply->fresh()->is_deleted);
        $this->assertSame(PostReportStatus::REVIEWED, $report->fresh()->status);
        $bannedAuthor = $reply->author->fresh();
        $this->assertTrue((bool) $bannedAuthor->is_banned);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $bannedAuthor->banned_until->timestamp, 120);
    }

    public function test_forums_index_queue_offers_remove_thread_only_when_it_is_the_sole_reply(): void
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general-sole-reply-queue', 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id, 'name' => 'Sole Reply Forum', 'slug' => 'sole-reply-forum', 'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'Sole Reply Thread',
            'slug' => 'sole-reply-thread', 'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        $only = Post::query()->create([
            'thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'the only post here',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        PostReport::query()->create([
            'post_id' => $only->id, 'thread_id' => $thread->id,
            'reporter_id' => User::factory()->create()->id, 'reason' => 'spam',
            'status' => PostReportStatus::OPEN->value,
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get('/forums')
            ->assertOk()
            ->assertSee('name="outcome" value="remove_thread"', false);
    }

    public function test_forums_index_moderation_queue_offers_reject_permanently_through_the_delete_dialog(): void
    {
        $this->markTestSkipped('Needs thread creation and moderation: slice S3 (forum writing).');

        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-reject-permanently-queue',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Queued Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Pending Queue Thread',
            'slug' => 'pending-queue-thread-reject-permanently',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Pending starter',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get('/forums')
            ->assertOk()
            ->assertSee(__('forum.thread_delete.reject_open'))
            ->assertSee('data-thread-delete-open', false)
            ->assertSee('data-thread-delete-dialog', false);

        $this->actingAs($moderator)->post(route('threads.moderate', $thread), [
            'action' => 'reject_permanently',
            'reason' => 'Spam.',
            'return_to' => '/forums#forum-moderation-queue',
        ])->assertRedirect('/forums#forum-moderation-queue');

        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);

        $this->actingAs($moderator)
            ->get('/forums')
            ->assertOk()
            ->assertDontSee('Pending Queue Thread');
    }

    public function test_staff_preview_mode_hides_forum_moderation_queue(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-preview-queue',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Queued Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Pending Queue Thread',
            'slug' => 'pending-queue-thread-preview',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Pending starter',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get('/forums')
            ->assertOk()
            ->assertDontSee('Forum Moderation Queue')
            ->assertDontSee('Pending Queue Thread');
    }

    public function test_moderation_queue_shows_on_forum_and_thread_pages_not_just_the_index(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-queue-everywhere',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Queued Everywhere Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();

        $pending = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Pending Everywhere Thread',
            'slug' => 'pending-everywhere-thread',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);
        Post::query()->create([
            'thread_id' => $pending->id,
            'author_id' => $author->id,
            'body' => 'Pending starter',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $visible = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Visible Everywhere Thread',
            'slug' => 'visible-everywhere-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        Post::query()->create([
            'thread_id' => $visible->id,
            'author_id' => $author->id,
            'body' => 'Visible starter',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Forum Moderation Queue')
            ->assertSee('Pending Everywhere Thread');

        $this->actingAs($moderator)
            ->get(route('threads.show', $visible))
            ->assertOk()
            ->assertSee('Forum Moderation Queue')
            ->assertSee('Pending Everywhere Thread');

        // A normal member never sees it, on any page.
        $this->actingAs(User::factory()->create())
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertDontSee('Forum Moderation Queue');

        // Nor does a moderator previewing the site as a member.
        $this->actingAs($moderator)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('threads.show', $visible))
            ->assertOk()
            ->assertDontSee('Forum Moderation Queue');
    }

    public function test_forums_index_contains_links_to_forum_and_thread_pages(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome Thread',
            'slug' => 'welcome-thread',
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Hello world',
        ]);

        $response = $this->get('/forums');

        $response->assertOk();
        $response->assertSee(route('forums.show', $forum), false);
        $response->assertSee(route('threads.show', $thread), false);
    }

    public function test_forums_index_uses_distinct_group_header_and_list_backgrounds(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Styled Group',
            'slug' => 'styled-group',
            'is_active' => true,
        ]);

        $this->createForum($group, 'Styled Forum', ForumVisibility::PUBLIC);

        $this->get('/forums')
            ->assertOk()
            ->assertSee('class="ui-surface-panel border-b border-[#d8dce2] px-4 py-3"', false)
            ->assertSee('class="divide-y divide-[#d8dce2] bg-white"', false);
    }

    public function test_forum_totals_widget_can_be_toggled_off_via_config(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-totals-toggle',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Totals Toggle Forum', ForumVisibility::PUBLIC);

        config()->set('features.forum_totals_widget', true);
        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Threads')
            ->assertSee('Posts');

        config()->set('features.forum_totals_widget', false);
        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertDontSee('text-2xl font-semibold', false);
    }

    public function test_thread_pagination_summary_can_be_toggled_off_via_config(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-pagination-toggle',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Pagination Toggle Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();

        for ($i = 0; $i < 31; $i++) {
            Thread::query()->create([
                'forum_id' => $forum->id,
                'author_id' => $author->id,
                'title' => 'Pagination toggle thread '.$i,
                'slug' => 'pagination-toggle-thread-'.$i,
            ]);
        }

        config()->set('features.thread_pagination_summary', true);
        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Page 1 of 2');

        config()->set('features.thread_pagination_summary', false);
        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertDontSee('Page 1 of 2');
    }

    public function test_forum_and_thread_pages_are_available(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome Thread',
            'slug' => 'welcome-thread-2',
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $this->get(route('forums.show', $forum))->assertOk()->assertSee('Welcome Thread');
        $this->get(route('threads.show', $thread))->assertOk()->assertSee('First post');
    }

    public function test_forum_show_thread_title_links_to_first_post_and_shows_latest_post_shortcut(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Latest Post Link Thread',
            'slug' => 'latest-post-link-thread',
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
        ]);

        $latestPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Latest reply',
        ]);

        $thread->forceFill([
            'last_post_author_id' => $author->id,
            'last_post_at' => $latestPost->created_at,
        ])->save();

        $firstPost = Post::query()->where('thread_id', $thread->id)->orderBy('id')->first();
        $this->assertNotNull($firstPost);

        $firstUrl = route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$firstPost->id;
        $latestUrl = route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$latestPost->id;
        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee($firstUrl, false)
            ->assertDontSee('>Latest post<', false)
            ->assertSee($latestUrl, false);
    }

    public function test_forum_show_moves_stats_below_thread_list_and_removes_main_view_shortcuts(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-layout',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Layout Cleanup Thread',
            'slug' => 'layout-cleanup-thread',
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
        ]);

        // This test covers the stats widget's placement/layout, not whether it
        // shows at all — force it on regardless of the feature flag's default.
        config()->set('features.forum_totals_widget', true);

        $this->get(route('forums.show', $forum))
            ->assertOk()
            ->assertDontSee('>First post<', false)
            ->assertDontSee('>Latest post<', false)
            ->assertSeeInOrder([
                'Layout Cleanup Thread',
                '<div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Threads</div>',
            ], false)
            ->assertSee('class="mb-4 border border-[#d8dce2] bg-white p-4"', false)
            ->assertSee('class="border border-[#d8dce2] bg-white p-3"', false);
    }

    public function test_unverified_user_sees_verification_banner_on_forum_pages(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome Thread',
            'slug' => 'welcome-thread-3',
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Your email is not verified.')
            ->assertSee('Verify email');

        $this->actingAs($unverifiedUser)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Your email is not verified.')
            ->assertSee('Verify email');
    }

    public function test_verified_user_does_not_see_verification_banner_on_forum_pages(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome Thread',
            'slug' => 'welcome-thread-4',
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $verifiedUser = User::factory()->create();

        $this->actingAs($verifiedUser)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertDontSee('Your email is not verified.');

        $this->actingAs($verifiedUser)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Your email is not verified.');
    }

    private function createForum(ForumGroup $group, string $name, ForumVisibility $visibility): Forum
    {
        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'visibility' => $visibility,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
    }
}
