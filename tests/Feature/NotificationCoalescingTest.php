<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * Repeat replies to one followed thread used to write a new bell row every
 * time - ten replies, ten rows. CoalescingDatabaseChannel folds them into one
 * row (with a running count) as long as it stays unread.
 */
class NotificationCoalescingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_two_replies_to_a_followed_thread_coalesce_into_one_unread_bell_row(): void
    {
        $thread = $this->publicThread();
        $replier = User::factory()->create();
        $subscriber = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $subscriber->id]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), ['body' => 'first reply'])->assertRedirect();
        $this->actingAs($replier)->post(route('threads.reply', $thread), ['body' => 'second reply'])->assertRedirect();

        $rows = $subscriber->notifications()->get();
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows->first()->data['count']);
        $this->assertStringContainsString('second reply', $rows->first()->data['body']);
        $this->assertNull($rows->first()->read_at);
    }

    public function test_a_reply_after_the_coalesced_row_is_read_starts_a_fresh_row(): void
    {
        $thread = $this->publicThread();
        $replier = User::factory()->create();
        $subscriber = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $subscriber->id]);

        $this->actingAs($replier)->post(route('threads.reply', $thread), ['body' => 'first reply'])->assertRedirect();
        $subscriber->notifications()->first()->markAsRead();

        $this->actingAs($replier)->post(route('threads.reply', $thread), ['body' => 'second reply'])->assertRedirect();

        $this->assertSame(2, $subscriber->notifications()->count());

        $read = $subscriber->notifications()->whereNotNull('read_at')->firstOrFail();
        $this->assertSame(1, (int) $read->data['count']);
        $this->assertStringContainsString('first reply', $read->data['body']);

        $unread = $subscriber->notifications()->whereNull('read_at')->firstOrFail();
        $this->assertSame(1, (int) $unread->data['count']);
        $this->assertStringContainsString('second reply', $unread->data['body']);
    }

    public function test_replies_to_two_different_followed_threads_do_not_coalesce_together(): void
    {
        $threadA = $this->publicThread();
        $threadB = $this->publicThread();
        $replier = User::factory()->create();
        $subscriber = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $threadA->id, 'user_id' => $subscriber->id]);
        ThreadSubscription::query()->create(['thread_id' => $threadB->id, 'user_id' => $subscriber->id]);

        $this->actingAs($replier)->post(route('threads.reply', $threadA), ['body' => 'reply in A'])->assertRedirect();
        $this->actingAs($replier)->post(route('threads.reply', $threadB), ['body' => 'reply in B'])->assertRedirect();

        $rows = $subscriber->notifications()->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn ($row): bool => (int) $row->data['count'] === 1));
    }

    private function publicThread(): Thread
    {
        $group = ForumGroup::query()->create(['name' => 'G', 'slug' => 'g-'.Str::lower(Str::random(6)), 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'F',
            'slug' => 'f-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'T',
            'slug' => 't-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'first post',
            'content_format' => 'markdown',
            'moderation_status' => ContentModerationStatus::APPROVED->value,
            'approved_at' => now(),
        ]);

        return $thread;
    }
}
