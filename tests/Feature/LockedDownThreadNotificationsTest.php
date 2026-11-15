<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ForumThreadCreatedNotification;
use App\Notifications\ThreadDigestNotification;
use App\Notifications\ThreadUpdatedNotification;
use Filagora\Forums\Enums\DeliveryMode;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\Forums\Models\ThreadSubscriptionDigestItem;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * Following, queued mail and bell rows created while a forum was readable outlive it being locked
 * down. None of them may be delivered, or lead a member into a 403, once they can no longer read
 * the thread.
 */
class LockedDownThreadNotificationsTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    /**
     * @return array{0: Thread, 1: User, 2: Post}
     */
    private function thread(ForumVisibility $visibility): array
    {
        $group = ForumGroup::query()->create(['name' => 'Security', 'slug' => 'security', 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Reports',
            'slug' => 'reports-'.Str::lower(Str::random(6)),
            'visibility' => $visibility,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'show_when_empty' => true,
            'is_read_only' => false,
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create(['forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'Report', 'slug' => 'report-'.Str::lower(Str::random(6))]);
        $post = Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'Original report']);

        return [$thread->refresh(), $author, $post];
    }

    public function test_a_reply_in_a_locked_down_thread_adds_no_bell_row_for_a_follower_who_cannot_read_it(): void
    {
        [$thread, $author] = $this->thread(ForumVisibility::STAFF_AUTHOR_ONLY);
        $bystander = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $bystander->id]);

        $this->actingAs($author)->post(route('threads.reply', $thread), ['body' => 'Follow-up from the author'])->assertRedirect();

        $this->assertSame(0, $bystander->notifications()->count());
    }

    public function test_a_bell_row_for_a_thread_update_records_the_thread(): void
    {
        [$thread, $author] = $this->thread(ForumVisibility::PUBLIC);
        $follower = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $follower->id]);

        $this->actingAs($author)->post(route('threads.reply', $thread), ['body' => 'A reply'])->assertRedirect();

        $this->assertSame($thread->id, (int) $follower->notifications()->firstOrFail()->data['thread_id']);
    }

    public function test_every_queued_thread_notification_is_suppressed_for_a_member_who_cannot_read_the_thread(): void
    {
        [$thread, , $post] = $this->thread(ForumVisibility::STAFF_AUTHOR_ONLY);
        $bystander = User::factory()->create();
        $item = ThreadSubscriptionDigestItem::query()->create(['user_id' => $bystander->id, 'thread_id' => $thread->id, 'post_id' => $post->id]);

        $this->assertSame([], (new ThreadUpdatedNotification($thread, $post))->via($bystander));
        $this->assertSame([], (new ForumThreadCreatedNotification($thread))->via($bystander));
        $this->assertSame([], (new ThreadDigestNotification(DeliveryMode::DAILY, [$item->load('thread', 'post')]))->via($bystander));
    }

    public function test_a_queued_update_is_still_sent_to_a_member_who_can_read_the_thread(): void
    {
        [$thread, , $post] = $this->thread(ForumVisibility::PUBLIC);
        $reader = User::factory()->create();

        $this->assertSame(['mail'], (new ThreadUpdatedNotification($thread, $post))->via($reader));
    }

    public function test_opening_a_notification_for_an_unreadable_thread_does_not_follow_the_link(): void
    {
        [$thread] = $this->thread(ForumVisibility::STAFF_AUTHOR_ONLY);
        $bystander = User::factory()->create();
        $stored = $this->notificationFor($bystander, ['thread_id' => $thread->id, 'url' => route('threads.show', $thread)]);
        $legacy = $this->notificationFor($bystander, ['url' => route('threads.show', $thread).'#post-9']);

        foreach ([$stored, $legacy] as $notification) {
            $this->actingAs($bystander)
                ->get(route('notifications.open', $notification->id))
                ->assertRedirect(route('notifications.index'))
                ->assertSessionHas('profile_status', __('notifications.bell.unavailable'));

            $this->assertNotNull($notification->refresh()->read_at);
        }
    }

    public function test_opening_a_notification_for_a_readable_thread_still_follows_the_link(): void
    {
        [$thread, $author] = $this->thread(ForumVisibility::STAFF_AUTHOR_ONLY);
        $notification = $this->notificationFor($author, ['thread_id' => $thread->id, 'url' => route('threads.show', $thread)]);

        $this->actingAs($author)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect('/threads/'.$thread->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notificationFor(User $user, array $data): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\BellNotification',
            'data' => $data + ['title' => 'T', 'body' => 'B', 'category' => 'forum.thread_updates_instant'],
        ]);
    }
}
