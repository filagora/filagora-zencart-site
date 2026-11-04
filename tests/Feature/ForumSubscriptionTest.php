<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Channels\CoalescingDatabaseChannel;
use App\Notifications\ForumThreadCreatedNotification;
use App\Services\ContentRelocationService;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ForumSubscription;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\Forums\Services\ForumSubscriptions;
use Filagora\Messages\Models\IgnoredUser;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Push\Models\PushSubscription;
use Filagora\Push\Notifications\WebPushAlert;
use Filagora\Push\Services\PushSubscriptions;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ForumSubscriptionTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_member_can_subscribe_and_unsubscribe_a_forum(): void
    {
        $forum = $this->forum();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('forums.subscribe', $forum))
            ->assertRedirect(route('forums.show', $forum));
        $this->assertDatabaseHas('forum_subscriptions', ['forum_id' => $forum->id, 'user_id' => $user->id]);

        $this->actingAs($user)->get(route('forums.show', $forum))->assertOk()->assertSee('Unsubscribe');
        $this->actingAs($user)->get(route('profile.show', ['tab' => 'subscriptions']))->assertOk()->assertSee('Forum Subscriptions')->assertSee($forum->name);

        $this->actingAs($user)
            ->post(route('forums.unsubscribe', $forum), ['redirect_to' => 'profile'])
            ->assertRedirect(route('profile.show', ['tab' => 'subscriptions']));
        $this->assertDatabaseMissing('forum_subscriptions', ['forum_id' => $forum->id, 'user_id' => $user->id]);
    }

    public function test_forums_index_offers_subscribe_and_unsubscribe_buttons(): void
    {
        $forum = $this->forum();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('forums.index'))
            ->assertOk()
            ->assertSee(route('forums.subscribe', $forum))
            ->assertSee('Subscribe');

        $this->subscribeTo($forum, $user);

        $this->actingAs($user)->get(route('forums.index'))
            ->assertOk()
            ->assertSee(route('forums.unsubscribe', $forum))
            ->assertSee('Unsubscribe');
    }

    public function test_guests_and_unverified_members_are_not_offered_the_button(): void
    {
        $forum = $this->forum();

        $this->get(route('forums.index'))->assertOk()->assertDontSee(route('forums.subscribe', $forum));
        $this->get(route('forums.show', $forum))->assertOk()->assertDontSee(route('forums.subscribe', $forum));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('forums.index'))
            ->assertOk()
            ->assertDontSee(route('forums.subscribe', $forum));
    }

    public function test_subscribing_from_the_forums_index_shows_a_confirmation_with_a_link(): void
    {
        $forum = $this->forum();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('forums.subscribe', $forum), ['return_to' => '/forums'])
            ->assertOk()
            ->assertSee(__('forum.flash.forum_subscribed'))
            ->assertSee('Manage subscriptions')
            ->assertSee(route('profile.show', ['tab' => 'subscriptions']));
    }

    public function test_an_outside_return_address_is_not_followed(): void
    {
        $forum = $this->forum();

        $this->actingAs(User::factory()->create())
            ->post(route('forums.subscribe', $forum), ['return_to' => '//example.test/phish'])
            ->assertRedirect(route('forums.show', $forum));
    }

    public function test_subscribing_is_idempotent(): void
    {
        $forum = $this->forum();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('forums.subscribe', $forum));
        $this->actingAs($user)->post(route('forums.subscribe', $forum));

        $this->assertSame(1, ForumSubscription::query()->count());
    }

    public function test_cannot_subscribe_to_a_forum_the_member_cannot_view(): void
    {
        $forum = $this->forum(['visibility' => ForumVisibility::STAFF_ONLY]);

        $this->actingAs(User::factory()->create())->post(route('forums.subscribe', $forum))->assertForbidden();
        $this->assertDatabaseCount('forum_subscriptions', 0);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $forum = $this->forum();

        $this->post(route('forums.subscribe', $forum))->assertRedirect();
        $this->assertDatabaseCount('forum_subscriptions', 0);
    }

    public function test_new_thread_notifies_subscribers_but_not_the_author(): void
    {
        Notification::fake();
        $forum = $this->forum();
        $author = User::factory()->create();
        $subscriber = User::factory()->create();
        $this->subscribeTo($forum, $author, $subscriber);

        $this->actingAs($author)->post(route('threads.create', $forum), [
            'title' => 'Brand new thread',
            'body' => 'Opening post body',
        ])->assertRedirect();

        Notification::assertSentTo($subscriber, ForumThreadCreatedNotification::class);
        Notification::assertNotSentTo($author, ForumThreadCreatedNotification::class);
    }

    public function test_subscribers_of_other_forums_are_not_notified(): void
    {
        Notification::fake();
        $forum = $this->forum();
        $other = $this->forum();
        $bystander = User::factory()->create();
        $this->subscribeTo($other, $bystander);

        $this->actingAs(User::factory()->create())->post(route('threads.create', $forum), [
            'title' => 'Brand new thread',
            'body' => 'Opening post body',
        ]);

        Notification::assertNotSentTo($bystander, ForumThreadCreatedNotification::class);
    }

    public function test_pending_thread_notifies_only_once_a_moderator_approves_it(): void
    {
        Notification::fake();
        $forum = $this->forum(['requires_thread_approval' => true]);
        $subscriber = User::factory()->create();
        $this->subscribeTo($forum, $subscriber);

        $this->actingAs(User::factory()->create())->post(route('threads.create', $forum), [
            'title' => 'Needs approval',
            'body' => 'Opening post body',
        ]);
        $thread = Thread::query()->where('title', 'Needs approval')->firstOrFail();
        $this->assertSame(ContentModerationStatus::PENDING, $thread->moderation_status);
        Notification::assertNotSentTo($subscriber, ForumThreadCreatedNotification::class);

        $this->actingAs($this->moderator())->post(route('threads.moderate', $thread), ['action' => 'approve'])->assertRedirect();

        Notification::assertSentTo($subscriber, ForumThreadCreatedNotification::class);
    }

    public function test_thread_moved_into_a_forum_notifies_its_subscribers(): void
    {
        Notification::fake();
        $destination = $this->forum();
        $subscriber = User::factory()->create();
        $this->subscribeTo($destination, $subscriber);
        $thread = $this->thread($this->forum(), User::factory()->create());

        $this->actingAs($this->moderator())->post(route('threads.moderate', $thread), [
            'action' => 'move',
            'forum_id' => $destination->id,
        ])->assertRedirect();

        Notification::assertSentTo($subscriber, ForumThreadCreatedNotification::class);
    }

    public function test_a_reply_promoted_to_a_thread_notifies_the_destination_forums_subscribers(): void
    {
        Notification::fake();
        $destination = $this->forum();
        $subscriber = User::factory()->create();
        $this->subscribeTo($destination, $subscriber);
        $thread = $this->thread($this->forum(), User::factory()->create());
        $reply = Post::query()->create(['thread_id' => $thread->id, 'author_id' => User::factory()->create()->id, 'body' => 'A reply']);

        app(ContentRelocationService::class)->promotePost($reply, $destination, 'From a reply', null, $this->moderator());

        Notification::assertSentTo($subscriber, ForumThreadCreatedNotification::class);
    }

    public function test_several_replies_promoted_together_notify_once(): void
    {
        Notification::fake();
        $destination = $this->forum();
        $subscriber = User::factory()->create();
        $this->subscribeTo($destination, $subscriber);
        $thread = $this->thread($this->forum(), User::factory()->create());
        $author = User::factory()->create();
        $replies = collect(['One', 'Two'])->map(fn (string $body) => Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => $body]));

        app(ContentRelocationService::class)->promotePostsToThread($replies, $destination, 'From replies', null, $this->moderator());

        Notification::assertSentToTimes($subscriber, ForumThreadCreatedNotification::class, 1);
    }

    public function test_subscriber_who_lost_access_to_a_paid_forum_is_not_notified(): void
    {
        Notification::fake();
        $forum = $this->forum(['visibility' => ForumVisibility::PAID_ONLY]);
        $lapsed = User::factory()->create();
        $this->subscribeTo($forum, $lapsed);

        $this->announce($this->thread($forum, User::factory()->create()));

        Notification::assertNothingSent();
    }

    public function test_subscriber_who_ignores_the_author_is_not_notified(): void
    {
        Notification::fake();
        $forum = $this->forum();
        $author = User::factory()->create();
        $ignorer = User::factory()->create();
        $subscriber = User::factory()->create();
        $this->subscribeTo($forum, $ignorer, $subscriber);
        IgnoredUser::query()->create(['user_id' => $ignorer->id, 'ignored_user_id' => $author->id]);

        $this->announce($this->thread($forum, $author));

        Notification::assertNotSentTo($ignorer, ForumThreadCreatedNotification::class);
        Notification::assertSentTo($subscriber, ForumThreadCreatedNotification::class);
    }

    public function test_subscriber_already_subscribed_to_the_thread_is_not_notified_twice(): void
    {
        Notification::fake();
        $forum = $this->forum();
        $subscriber = User::factory()->create();
        $this->subscribeTo($forum, $subscriber);
        $thread = $this->thread($forum, User::factory()->create());
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $subscriber->id]);

        $this->announce($thread);

        Notification::assertNothingSent();
    }

    public function test_unapproved_thread_is_never_announced(): void
    {
        Notification::fake();
        $forum = $this->forum();
        $this->subscribeTo($forum, User::factory()->create());

        $this->announce($this->thread($forum, User::factory()->create(), ContentModerationStatus::PENDING));

        Notification::assertNothingSent();
    }

    public function test_channels_follow_the_category_preferences(): void
    {
        $subscriber = $this->emailableUser();
        $thread = $this->thread($this->forum(), User::factory()->create());

        $channels = (new ForumThreadCreatedNotification($thread))->via($subscriber);

        $this->assertContains('mail', $channels);
        $this->assertContains(CoalescingDatabaseChannel::class, $channels);

        app(Preferences::class)->set($subscriber, ForumThreadCreatedNotification::CATEGORY, NotificationChannel::EMAIL, false);

        $this->assertNotContains('mail', (new ForumThreadCreatedNotification($thread))->via($subscriber->fresh()));
    }

    public function test_a_subscriber_with_a_browser_subscription_gets_a_push(): void
    {
        Notification::fake();
        $forum = $this->forum();
        $subscriber = User::factory()->create();
        $this->subscribeTo($forum, $subscriber);
        $this->mock(PushSubscriptions::class)->shouldReceive('for')->andReturn(new EloquentCollection([new PushSubscription]));

        $this->announce($this->thread($forum, User::factory()->create()));

        Notification::assertSentOnDemand(WebPushAlert::class);
    }

    public function test_turning_push_off_for_the_category_stops_the_push(): void
    {
        Notification::fake();
        $forum = $this->forum();
        $subscriber = User::factory()->create();
        $this->subscribeTo($forum, $subscriber);
        app(Preferences::class)->set($subscriber, ForumThreadCreatedNotification::CATEGORY, NotificationChannel::PUSH, false);
        $this->mock(PushSubscriptions::class)->shouldReceive('for')->andReturn(new EloquentCollection([new PushSubscription]));

        $this->announce($this->thread($forum, User::factory()->create()));

        Notification::assertNothingSentTo(new AnonymousNotifiable);
    }

    public function test_mail_renders(): void
    {
        $subscriber = User::factory()->create();
        $forum = $this->forum();
        $thread = $this->thread($forum, User::factory()->create());

        $mail = (new ForumThreadCreatedNotification($thread))->toMail($subscriber);

        $this->assertStringContainsString($forum->name, $mail->subject);
        $this->assertStringContainsString($thread->title, (string) $mail->render());
    }

    public function test_markdown_in_a_post_cannot_add_links_or_images_to_the_mail(): void
    {
        $thread = $this->thread($this->forum(), User::factory()->create());
        Post::query()->where('thread_id', $thread->id)->update(['body' => '[Verify your account](https://evil.test) ![](https://evil.test/pixel.gif)']);
        $thread->update(['title' => '[Click](https://evil.test)']);

        $html = (string) (new ForumThreadCreatedNotification($thread))->toMail(User::factory()->create())->render();

        $this->assertStringNotContainsString('href="https://evil.test', $html);
        $this->assertStringNotContainsString('<img src="https://evil.test', $html);
        $this->assertStringContainsString('Verify your account', $html);
    }

    public function test_the_bell_row_folds_threads_of_one_forum_together(): void
    {
        $thread = $this->thread($this->forum(), User::factory()->create());

        $row = (new ForumThreadCreatedNotification($thread))->toArray(User::factory()->create());

        $this->assertSame('forum:'.$thread->forum_id, $row['coalesce_key']);
        $this->assertSame(ForumThreadCreatedNotification::CATEGORY, $row['category']);
        $this->assertSame((int) $thread->author_id, $row['actor_id']);
        $this->assertStringContainsString('Opening post', $row['body']);
    }

    public function test_a_deleted_opening_post_is_not_quoted(): void
    {
        $thread = $this->thread($this->forum(), User::factory()->create());
        Post::query()->where('thread_id', $thread->id)->update(['is_deleted' => true]);

        $row = (new ForumThreadCreatedNotification($thread))->toArray(User::factory()->create());

        $this->assertStringNotContainsString('Opening post', $row['body']);
        $this->assertStringContainsString(__('notifications.forum_thread_created.fallback_excerpt'), $row['body']);
    }

    public function test_imported_entities_are_decoded_for_readers(): void
    {
        $forum = $this->forum(['name' => 'News &amp; Announcements']);
        $thread = $this->thread($forum, User::factory()->create());
        $thread->update(['title' => 'Tom &amp; Jerry']);

        $notification = new ForumThreadCreatedNotification($thread);

        $this->assertSame('New thread in News & Announcements: Tom & Jerry', $notification->toMail(User::factory()->create())->subject);
        $this->assertSame('Tom & Jerry', $notification->toArray(User::factory()->create())['title']);
    }

    public function test_a_malformed_return_address_falls_back_to_the_forum(): void
    {
        $forum = $this->forum();

        $this->actingAs(User::factory()->create())
            ->post(route('forums.subscribe', $forum), ['return_to' => ['/forums']])
            ->assertRedirect(route('forums.show', $forum));
    }

    public function test_a_member_who_sees_only_their_own_threads_cannot_subscribe(): void
    {
        $forum = $this->forum(['visibility' => ForumVisibility::PAID_OR_AUTHOR]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('forums.index'))->assertOk()->assertDontSee(route('forums.subscribe', $forum));
        $this->actingAs($user)->get(route('forums.show', $forum))->assertOk()->assertDontSee(route('forums.subscribe', $forum));
        $this->actingAs($user)->post(route('forums.subscribe', $forum))->assertForbidden();
        $this->assertDatabaseCount('forum_subscriptions', 0);
    }

    public function test_an_existing_subscriber_there_can_still_leave(): void
    {
        $forum = $this->forum(['visibility' => ForumVisibility::PAID_OR_AUTHOR]);
        $user = User::factory()->create();
        $this->subscribeTo($forum, $user);

        $this->actingAs($user)->get(route('forums.index'))->assertOk()->assertSee(route('forums.unsubscribe', $forum));
        $this->actingAs($user)->get(route('forums.show', $forum))->assertOk()->assertSee(route('forums.unsubscribe', $forum));
    }

    public function test_the_profile_leaves_out_forums_the_member_can_no_longer_see(): void
    {
        $visible = $this->forum();
        $hidden = $this->forum(['visibility' => ForumVisibility::STAFF_ONLY]);
        $user = User::factory()->create();
        $this->subscribeTo($visible, $user);
        $this->subscribeTo($hidden, $user);

        $this->actingAs($user)->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);
    }

    public function test_deleting_a_forum_cascades_its_subscriptions(): void
    {
        $forum = $this->forum();
        $this->subscribeTo($forum, User::factory()->create());

        $forum->forceDelete();

        $this->assertDatabaseCount('forum_subscriptions', 0);
    }

    private function announce(Thread $thread): void
    {
        app(ForumSubscriptions::class)->announce($thread);
    }

    private function subscribeTo(Forum $forum, User ...$users): void
    {
        foreach ($users as $user) {
            ForumSubscription::query()->create(['forum_id' => $forum->id, 'user_id' => $user->id]);
        }
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }

    private function emailableUser(): User
    {
        $user = User::factory()->create(['receives_email' => true]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->getKey(),
            'entitlement_key' => (string) config('filagora-zencart.notifications.email_entitlement'),
            'grant_type' => 'registration',
            'source_type' => 'registration',
            'starts_at' => now(),
            'reason' => 'Test default.',
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function forum(array $attributes = []): Forum
    {
        $group = ForumGroup::query()->firstOrCreate(['slug' => 'general-forum-subscriptions'], ['name' => 'General', 'is_active' => true]);
        $slug = 'forum-'.Str::lower(Str::random(8));

        return Forum::query()->create($attributes + [
            'forum_group_id' => $group->id,
            'name' => 'Forum '.$slug,
            'slug' => $slug,
            'is_active' => true,
            'is_read_only' => false,
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
        ]);
    }

    private function thread(Forum $forum, User $author, ContentModerationStatus $status = ContentModerationStatus::APPROVED): Thread
    {
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread in '.$forum->slug,
            'slug' => 'thread-'.Str::lower(Str::random(8)),
            'moderation_status' => $status,
        ]);

        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'Opening post', 'moderation_status' => $status]);

        return $thread;
    }
}
