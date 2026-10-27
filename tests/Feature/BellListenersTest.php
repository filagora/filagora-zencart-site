<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PostReportSubmittedNotification;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Events\PostModerated;
use Filagora\Forums\Events\PostReported;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\StandardCategories;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * The site's in-app side of what the packages announce. The mail is the packages' and is tested
 * there; these check the bell rows the site adds, and who gets them.
 */
class BellListenersTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    /**
     * @return array{0: Thread, 1: User}
     */
    private function thread(int $posts = 1): array
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $forum = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'Support', 'slug' => 'support-'.Str::lower(Str::random(6)), 'visibility' => ForumVisibility::PUBLIC, 'posting_policy' => ForumPostingPolicy::REGISTERED, 'is_read_only' => false, 'is_active' => true]);
        $author = User::factory()->create();
        $thread = Thread::query()->create(['forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'Followed Thread', 'slug' => 'followed-'.Str::lower(Str::random(6)), 'reply_count' => $posts - 1]);

        for ($i = 1; $i <= $posts; $i++) {
            Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => "Post {$i}"]);
        }

        return [$thread, $author];
    }

    private function follow(Thread $thread, User $user): void
    {
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $user->id]);
    }

    public function test_a_reply_to_a_followed_thread_adds_a_bell_row_that_links_to_the_post(): void
    {
        [$thread] = $this->thread(25);
        $follower = User::factory()->create();
        $replier = User::factory()->create();
        $this->follow($thread, $follower);

        $this->actingAs($replier)->post(route('threads.reply', $thread), ['body' => 'A brand new reply'])->assertRedirect();

        $row = $follower->notifications()->firstOrFail();
        $this->assertSame('Followed Thread', $row->data['title']);
        $this->assertStringContainsString('A brand new reply', $row->data['body']);
        $this->assertStringContainsString('page=2', $row->data['url']);
        $this->assertMatchesRegularExpression('/#post-\d+$/', $row->data['url']);
        $this->assertSame($replier->id, $row->data['actor_id']);
        $this->assertSame(0, $replier->notifications()->count());
    }

    public function test_a_follower_who_turned_the_bell_off_for_thread_updates_gets_no_row(): void
    {
        [$thread] = $this->thread();
        $follower = User::factory()->create();
        $this->follow($thread, $follower);
        app(Preferences::class)->set($follower, StandardCategories::THREAD_UPDATES_INSTANT, NotificationChannel::IN_APP, false, $follower);

        $this->actingAs(User::factory()->create())->post(route('threads.reply', $thread), ['body' => 'Hello there'])->assertRedirect();

        $this->assertSame(0, $follower->notifications()->count());
    }

    public function test_a_follower_whose_email_is_suppressed_is_not_told_at_all(): void
    {
        [$thread] = $this->thread();
        $follower = User::factory()->create(['receives_email' => false]);
        $this->follow($thread, $follower);

        $this->actingAs(User::factory()->create())->post(route('threads.reply', $thread), ['body' => 'Hello there'])->assertRedirect();

        $this->assertSame(0, $follower->notifications()->count()); // the forums package treats a suppressed address as "off"; the old site still rang the bell
    }

    public function test_a_mention_adds_a_bell_row_for_the_mentioned_member(): void
    {
        [$thread] = $this->thread();
        $mentioned = User::factory()->create(['username' => 'zencartfan']);

        $this->actingAs(User::factory()->create(['username' => 'helper']))->post(route('threads.reply', $thread), ['body' => 'Thanks @zencartfan for the fix'])->assertRedirect();

        $row = $mentioned->notifications()->firstOrFail();
        $this->assertSame('helper mentioned you', $row->data['title']);
        $this->assertStringContainsString('Thanks', $row->data['body']);
    }

    public function test_a_moderator_removing_a_post_tells_its_author(): void
    {
        [$thread] = $this->thread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $author = $post->author;
        $moderator = User::factory()->create();

        PostModerated::dispatch($post, $moderator, 'delete', 'Off topic.');

        $row = $author->notifications()->firstOrFail();
        $this->assertSame('A moderator removed your post', $row->data['title']);
        $this->assertSame('Off topic.', $row->data['body']);
        $this->assertSame(route('threads.show', $thread), $row->data['url']);
    }

    public function test_a_moderator_acting_on_their_own_post_tells_nobody(): void
    {
        [$thread] = $this->thread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();

        PostModerated::dispatch($post, $post->author, 'delete', 'Duplicate.');

        $this->assertSame(0, $post->author->notifications()->count());
    }

    public function test_approving_or_restoring_a_post_is_not_news_to_its_author(): void
    {
        [$thread] = $this->thread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();

        foreach (['approve', 'restore', 'promote'] as $action) {
            PostModerated::dispatch($post, User::factory()->create(), $action, null);
        }

        $this->assertSame(0, $post->author->notifications()->count());
    }

    public function test_deleting_several_posts_tells_each_author_once(): void
    {
        [$thread] = $this->thread(3);
        $moderator = User::factory()->create();
        $other = User::factory()->create();
        $posts = Post::query()->where('thread_id', $thread->id)->get();
        $posts[1]->forceFill(['author_id' => $other->id])->save();

        foreach ($posts as $post) {
            PostModerated::dispatch($post->fresh(), $moderator, 'delete', 'Spam');
        }

        $this->assertSame(2, $posts[0]->author->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_an_author_who_turned_off_moderation_notices_is_not_told(): void
    {
        [$thread] = $this->thread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        app(Preferences::class)->set($post->author, StandardCategories::MODERATION_OWN_CONTENT, NotificationChannel::IN_APP, false, $post->author);

        PostModerated::dispatch($post, User::factory()->create(), 'reject', null);

        $this->assertSame(0, $post->author->notifications()->count());
    }

    public function test_a_post_report_is_mailed_to_the_forum_support_address_when_one_is_set(): void
    {
        Notification::fake();
        config(['forums.support_email' => 'forum-support@example.test']);
        [$thread] = $this->thread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $report = PostReport::query()->create(['post_id' => $post->id, 'thread_id' => $thread->id, 'reporter_id' => User::factory()->create()->id, 'reason' => 'Spam', 'status' => 'open']);

        PostReported::dispatch($report);

        Notification::assertSentOnDemand(PostReportSubmittedNotification::class, fn ($notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'forum-support@example.test');
    }

    public function test_a_post_report_sends_nothing_without_a_support_address(): void
    {
        Notification::fake();
        config(['forums.support_email' => null]);
        [$thread] = $this->thread();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();
        $report = PostReport::query()->create(['post_id' => $post->id, 'thread_id' => $thread->id, 'reporter_id' => User::factory()->create()->id, 'reason' => 'Spam', 'status' => 'open']);

        PostReported::dispatch($report);

        Notification::assertNothingSent();
    }
}
