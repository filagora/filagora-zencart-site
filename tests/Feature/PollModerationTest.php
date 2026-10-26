<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Enums\PollStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Poll;
use Filagora\Forums\Models\ThreadModerationLog;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PollModerationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_can_close_a_poll(): void
    {
        [$thread, $poll] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->post(route('polls.moderate', $poll), [
            'action' => 'close',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#poll');
        $response->assertSessionHas('thread_status', __('forum.flash.poll_closed'));

        $poll->refresh();
        $this->assertSame(PollStatus::CLOSED, $poll->status);
        $this->assertNotNull($poll->closed_at);
        $this->assertSame($moderator->id, $poll->closed_by_id);

        $this->assertDatabaseHas('thread_moderation_logs', [
            'thread_id' => $thread->id,
            'moderator_id' => $moderator->id,
            'action' => 'poll_closed',
        ]);
    }

    public function test_staff_can_reopen_a_closed_poll(): void
    {
        [$thread, $poll] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();
        $poll->update(['status' => PollStatus::CLOSED, 'closed_at' => now(), 'closed_by_id' => $moderator->id]);

        $response = $this->actingAs($moderator)->post(route('polls.moderate', $poll), [
            'action' => 'reopen',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#poll');
        $response->assertSessionHas('thread_status', __('forum.flash.poll_reopened'));

        $poll->refresh();
        $this->assertSame(PollStatus::OPEN, $poll->status);
        $this->assertNull($poll->closed_at);
        $this->assertNull($poll->closed_by_id);

        $this->assertDatabaseHas('thread_moderation_logs', [
            'thread_id' => $thread->id,
            'moderator_id' => $moderator->id,
            'action' => 'poll_reopened',
        ]);
    }

    public function test_non_staff_cannot_close_or_reopen_a_poll(): void
    {
        [, $poll] = $this->createThreadWithPoll();
        $member = User::factory()->create();

        $response = $this->actingAs($member)->post(route('polls.moderate', $poll), [
            'action' => 'close',
        ]);

        $response->assertForbidden();
        $poll->refresh();
        $this->assertSame(PollStatus::OPEN, $poll->status);
    }

    public function test_closing_an_already_closed_poll_is_idempotent(): void
    {
        [$thread, $poll] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();
        $poll->update(['status' => PollStatus::CLOSED, 'closed_at' => now(), 'closed_by_id' => $moderator->id]);

        $response = $this->actingAs($moderator)->post(route('polls.moderate', $poll), [
            'action' => 'close',
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#poll');
        $poll->refresh();
        $this->assertSame(PollStatus::CLOSED, $poll->status);
        // Nothing changed, so nothing is logged (the old site logged every request).
        $this->assertSame(0, ThreadModerationLog::query()->where('thread_id', $thread->id)->where('action', 'poll_closed')->count());
    }

    /**
     * @return array{0: Thread, 1: Poll}
     */
    private function createThreadWithPoll(): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-pollmod-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-pollmod-'.Str::lower(Str::random(8)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Poll Moderation Test Thread',
            'slug' => 'poll-moderation-test-thread-'.Str::lower(Str::random(8)),
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $poll = Poll::query()->create([
            'thread_id' => $thread->id,
            'created_by_id' => $author->id,
            'status' => PollStatus::OPEN,
        ]);

        return [$thread, $poll];
    }

    private function createModerator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
