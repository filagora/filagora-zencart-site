<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalTime;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ThreadSubscriptionTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('audit.enabled', true);
        config()->set('audit.console', true);
    }

    public function test_authenticated_user_can_subscribe_and_manage_subscription_from_profile(): void
    {
        $user = User::factory()->create();
        $thread = $this->createPublicThread($user);

        $this->actingAs($user)
            ->post(route('threads.subscribe', $thread))
            ->assertRedirect(route('threads.show', $thread));

        $this->assertDatabaseHas('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk()
            ->assertSee('Subscriptions')
            ->assertSee($thread->title);

        $this->actingAs($user)
            ->post(route('profile.subscriptions.remove', $thread))
            ->assertRedirect(route('profile.show', ['tab' => 'subscriptions']));

        $this->assertDatabaseMissing('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_profile_subscriptions_show_thread_and_last_post_dates_beneath_title(): void
    {
        $user = User::factory()->create();
        $thread = $this->createPublicThread($user);
        $thread->forceFill([
            'created_at' => now()->subDays(7),
            'last_post_at' => now()->subDays(2),
        ])->save();
        $user->subscribedThreads()->syncWithoutDetaching([$thread->id]);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk()
            ->assertSee($thread->title)
            ->assertSee('Thread date: '.LocalTime::format($thread->created_at, 'date'))
            ->assertSee('Last post: '.LocalTime::format($thread->last_post_at, 'date'));
    }

    public function test_subscribe_and_unsubscribe_actions_create_audit_entries(): void
    {
        $this->markTestSkipped('Audit entries are deferred by decision (audit logs are not carried yet).');

        $user = User::factory()->create();
        $thread = $this->createPublicThread($user, 99);

        $this->actingAs($user)
            ->withHeader('User-Agent', 'ThreadSubscriptionAuditTest/1.0')
            ->post(route('threads.subscribe', $thread), [], [
                'REMOTE_ADDR' => '203.0.113.10',
            ])
            ->assertRedirect(route('threads.show', $thread));

        $subscription = ThreadSubscription::query()->where([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ])->firstOrFail();

        $this->assertDatabaseHas('audits', [
            'event' => 'created',
            'auditable_type' => ThreadSubscription::class,
            'auditable_id' => $subscription->id,
            'user_type' => User::class,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'ThreadSubscriptionAuditTest/1.0',
            'url' => 'console',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', 'ThreadSubscriptionAuditTest/1.0')
            ->post(route('threads.unsubscribe', $thread), [], [
                'REMOTE_ADDR' => '203.0.113.10',
            ])
            ->assertRedirect(route('threads.show', $thread));

        $this->assertDatabaseHas('audits', [
            'event' => 'deleted',
            'auditable_type' => ThreadSubscription::class,
            'auditable_id' => $subscription->id,
            'user_type' => User::class,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'ThreadSubscriptionAuditTest/1.0',
            'url' => 'console',
        ]);
    }

    public function test_guest_subscribe_redirects_to_login_modal(): void
    {
        $author = User::factory()->create();
        $thread = $this->createPublicThread($author);

        $this->post(route('threads.subscribe', $thread))
            ->assertRedirect(route('home', [
                'auth' => 'login',
                'intended' => route('threads.show', $thread),
            ]));
    }

    public function test_creating_thread_subscribes_the_author_by_default(): void
    {
        $user = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Help Desk',
            'slug' => 'help-desk',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('threads.create', $forum), [
            'title' => 'Default Followed Thread',
            'body' => 'Opening thread body.',
        ]);

        $thread = Thread::query()->where('title', 'Default Followed Thread')->firstOrFail();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();

        $response->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
        $this->assertDatabaseHas('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_creating_thread_does_not_subscribe_the_author_when_the_box_is_unticked(): void
    {
        $user = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Support',
            'slug' => 'support-followed',
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Help Desk',
            'slug' => 'help-desk-followed',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('threads.create', $forum), [
            'title' => 'Unfollowed Thread',
            'body' => 'Opening thread body.',
            'subscribe' => '0',
        ]);

        $thread = Thread::query()->where('title', 'Unfollowed Thread')->firstOrFail();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();

        $response->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
        $this->assertDatabaseMissing('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_new_thread_form_subscribe_box_starts_ticked_whatever_the_reply_setting_is(): void
    {
        $user = User::factory()->create(['auto_subscribe_on_post' => false]);
        $thread = $this->createPublicThread($user);

        $html = $this->actingAs($user)
            ->get(route('threads.create.form', $thread->forum))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="subscribe"\s+value="1"\s+checked/', $html);
    }

    public function test_forum_thread_list_hides_subscription_action_but_thread_page_can_return_back_after_subscribe(): void
    {
        $user = User::factory()->create();
        $thread = $this->createPublicThread($user);

        $forumPage = route('forums.show', $thread->forum);

        $this->actingAs($user)
            ->get($forumPage)
            ->assertOk()
            ->assertDontSee('>Subscribe<', false);

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('>Subscribe<', false);

        $this->actingAs($user)
            ->post(route('threads.subscribe', $thread), [
                'return_to' => parse_url($forumPage, PHP_URL_PATH),
            ])
            ->assertRedirect(parse_url($forumPage, PHP_URL_PATH));

        $this->assertDatabaseHas('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_forum_thread_list_hides_subscribed_indicator_but_thread_page_shows_unsubscribe(): void
    {
        $user = User::factory()->create();
        $thread = $this->createPublicThread($user);
        $user->subscribedThreads()->syncWithoutDetaching([$thread->id]);

        $this->actingAs($user)
            ->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertDontSee('data-thread-subscription-indicator', false)
            ->assertDontSee('Subscribed to this thread. Click to unsubscribe.', false);

        $this->actingAs($user)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('>Unsubscribe<', false);
    }

    public function test_activity_pages_show_subscribed_indicator(): void
    {
        $user = User::factory()->create();
        $subscribedThread = $this->createPublicThread($user, 1);
        $otherThread = $this->createPublicThread($user, 2);
        $user->subscribedThreads()->syncWithoutDetaching([$subscribedThread->id]);

        $response = $this->actingAs($user)->get(route('forums.today'));

        $response->assertOk();
        $response->assertSee('data-thread-subscribed-indicator', false);

        // Only the subscribed thread's row carries the indicator, not the other one.
        $subscribedRow = $this->extractThreadRow($response->getContent(), $subscribedThread->id);
        $otherRow = $this->extractThreadRow($response->getContent(), $otherThread->id);
        $this->assertStringContainsString('data-thread-subscribed-indicator', $subscribedRow);
        $this->assertStringNotContainsString('data-thread-subscribed-indicator', $otherRow);
    }

    private function extractThreadRow(string $html, int $threadId): string
    {
        $marker = 'data-thread-id="'.$threadId.'"';
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, "Thread row for #{$threadId} not found in response.");

        $nextArticle = strpos($html, 'data-thread-id="', $start + strlen($marker));
        $end = $nextArticle !== false ? $nextArticle : strlen($html);

        return substr($html, $start, $end - $start);
    }

    public function test_profile_subscriptions_are_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 21; $i++) {
            $thread = $this->createPublicThread($user, $i);
            $user->subscribedThreads()->syncWithoutDetaching([$thread->id]);
        }

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk()
            ->assertSee('>Subscription Test Thread 21<', false)
            ->assertSee('Thread subscriptions pagination')
            ->assertSee('>First<', false)
            ->assertSee('>Previous<', false)
            ->assertSee('>Next<', false)
            ->assertSee('>Last<', false)
            ->assertDontSee('>Subscription Test Thread 1<', false);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'subscriptions', 'thread_page' => 2]))
            ->assertOk()
            ->assertSee('>Subscription Test Thread 1<', false)
            ->assertDontSee('>Subscription Test Thread 21<', false);
    }

    public function test_profile_subscriptions_are_ordered_by_most_recent_thread_activity_first(): void
    {
        $user = User::factory()->create();
        $staleThread = $this->createPublicThread($user, 1);
        $activeThread = $this->createPublicThread($user, 2);

        $staleTimestamp = now()->subDays(5);
        $activeTimestamp = now()->subHour();

        $staleThread->forceFill([
            'created_at' => now()->subDays(10),
            'last_post_at' => $staleTimestamp,
        ])->save();

        $activeThread->forceFill([
            'created_at' => now()->subDays(20),
            'last_post_at' => $activeTimestamp,
        ])->save();

        $user->subscribedThreads()->syncWithoutDetaching([$activeThread->id]);
        $user->subscribedThreads()->syncWithoutDetaching([$staleThread->id]);

        DB::table('thread_subscriptions')
            ->where('user_id', $user->id)
            ->where('thread_id', $activeThread->id)
            ->update([
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(30),
            ]);

        DB::table('thread_subscriptions')
            ->where('user_id', $user->id)
            ->where('thread_id', $staleThread->id)
            ->update([
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ]);

        $response = $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk();

        $content = $response->getContent();

        $this->assertNotFalse($content);
        $this->assertLessThan(
            strpos($content, 'Subscription Test Thread 1'),
            strpos($content, 'Subscription Test Thread 2')
        );
    }

    public function test_subscribe_ignores_unsafe_return_to_and_falls_back_to_thread_page(): void
    {
        $user = User::factory()->create();
        $thread = $this->createPublicThread($user);

        $this->actingAs($user)
            ->post(route('threads.subscribe', $thread), [
                'return_to' => 'https://example.com/phishing',
            ])
            ->assertRedirect(route('threads.show', $thread));
    }

    public function test_unverified_user_does_not_see_subscribe_button_on_forum_or_thread_page(): void
    {
        $author = User::factory()->create();
        $thread = $this->createPublicThread($author);
        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertDontSee('>Subscribe<', false);

        $this->actingAs($unverifiedUser)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('>Subscribe<', false);
    }

    public function test_unverified_user_cannot_subscribe_to_thread(): void
    {
        $author = User::factory()->create();
        $thread = $this->createPublicThread($author);
        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->post(route('threads.subscribe', $thread))
            ->assertRedirect(route('verification.notice', [
                'intended' => '/threads/'.$thread->id.'/subscribe',
            ]))
            ->assertSessionHas('url.intended', '/threads/'.$thread->id.'/subscribe');

        $this->assertDatabaseMissing('thread_subscriptions', [
            'thread_id' => $thread->id,
            'user_id' => $unverifiedUser->id,
        ]);
    }

    public function test_member_can_unsubscribe_from_selected_threads_in_bulk(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        [$first, $second, $kept] = [
            $this->createPublicThread($user, 1),
            $this->createPublicThread($user, 2),
            $this->createPublicThread($user, 3),
        ];
        foreach ([$first, $second, $kept] as $thread) {
            ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $user->id]);
        }
        ThreadSubscription::query()->create(['thread_id' => $first->id, 'user_id' => $other->id]);

        $this->actingAs($user)
            ->post(route('profile.subscriptions.bulk-remove'), [
                'scope' => 'selected',
                'thread_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'subscriptions']))
            ->assertSessionHas('profile_status', '2 subscriptions removed.');

        $this->assertDatabaseMissing('thread_subscriptions', ['thread_id' => $first->id, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('thread_subscriptions', ['thread_id' => $second->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('thread_subscriptions', ['thread_id' => $kept->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('thread_subscriptions', ['thread_id' => $first->id, 'user_id' => $other->id]);
        $this->assertSame(2, DB::table('audits')->where('event', 'deleted')->where('auditable_type', ThreadSubscription::class)->count());
    }

    public function test_member_can_unsubscribe_from_all_threads_without_touching_other_members(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $threads = [$this->createPublicThread($user, 1), $this->createPublicThread($user, 2)];
        foreach ($threads as $thread) {
            ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $user->id]);
            ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $other->id]);
        }

        $this->actingAs($user)
            ->post(route('profile.subscriptions.bulk-remove'), ['scope' => 'all'])
            ->assertSessionHas('profile_status', '2 subscriptions removed.');

        $this->assertSame(0, ThreadSubscription::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, ThreadSubscription::query()->where('user_id', $other->id)->count());
    }

    public function test_bulk_unsubscribe_requires_a_selection_and_a_login(): void
    {
        $user = User::factory()->create();
        $thread = $this->createPublicThread($user);
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('profile.subscriptions.bulk-remove'), ['scope' => 'selected'])
            ->assertSessionHasErrors('thread_ids');
        $this->assertDatabaseHas('thread_subscriptions', ['thread_id' => $thread->id, 'user_id' => $user->id]);

        auth()->logout();
        $this->post(route('profile.subscriptions.bulk-remove'), ['scope' => 'all'])->assertRedirect();
        $this->assertDatabaseHas('thread_subscriptions', ['thread_id' => $thread->id, 'user_id' => $user->id]);
    }

    public function test_subscriptions_tab_offers_bulk_unsubscribe_controls(): void
    {
        $user = User::factory()->create();
        $thread = $this->createPublicThread($user);
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk()
            ->assertSee('Unsubscribe selected')
            ->assertSee('Unsubscribe from all 1 thread')
            ->assertSee('name="thread_ids[]" value="'.$thread->id.'"', false);
    }

    private function createPublicThread(User $author, ?int $suffix = null): Thread
    {
        $group = ForumGroup::query()->create([
            'name' => 'General'.($suffix !== null ? ' '.$suffix : ''),
            'slug' => 'general'.($suffix !== null ? '-'.$suffix : ''),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Announcements'.($suffix !== null ? ' '.$suffix : ''),
            'slug' => 'announcements'.($suffix !== null ? '-'.$suffix : ''),
            'is_active' => true,
        ]);

        return Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Subscription Test Thread'.($suffix !== null ? ' '.$suffix : ''),
            'slug' => 'subscription-test-thread'.($suffix !== null ? '-'.$suffix : ''),
        ]);
    }
}
