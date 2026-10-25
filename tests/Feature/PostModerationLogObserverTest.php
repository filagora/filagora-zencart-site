<?php

namespace Tests\Feature;

use App\Jobs\NotifyPostAuthorOfModerationJob;
use App\Models\User;
use App\Notifications\PostModeratedNotification;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\PostModerationLog;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * PostModerationLogObserver notifies a post's author of adverse moderator
 * action. It used to do this inline (a post()->with() query plus notify()
 * call per log row, on the request thread) which made a bulk moderation
 * action linear in the number of posts touched; it now dispatches a queued
 * job per row instead.
 */
class PostModerationLogObserverTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_deleting_a_post_queues_one_notification_job(): void
    {
        $this->markTestSkipped('Telling a post author that a moderator acted arrives with the notifications slice: S6 (a cutover requirement).');

        Queue::fake();
        [$thread, $post] = $this->threadWithReply();
        $moderator = $this->moderator();

        $this->actingAs($moderator)->post(route('posts.moderate', $post), [
            'action' => 'delete',
            'reason' => 'Spam',
            'return_to' => route('threads.show', $thread),
        ]);

        $logId = (int) PostModerationLog::query()->where('post_id', $post->id)->value('id');
        Queue::assertPushed(NotifyPostAuthorOfModerationJob::class, 1);
        Queue::assertPushed(fn (NotifyPostAuthorOfModerationJob $job): bool => $this->jobTargetsLog($job, $logId));
    }

    public function test_bulk_deleting_posts_queues_one_job_per_post_not_n_inline_notifications(): void
    {
        $this->markTestSkipped('Telling a post author that a moderator acted arrives with the notifications slice: S6 (a cutover requirement).');

        Queue::fake();
        $thread = $this->publicThread();
        $posts = collect(range(1, 3))->map(fn () => Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => User::factory()->create()->id,
            'body' => 'reply',
        ]));
        $moderator = $this->moderator();

        $this->actingAs($moderator)->post(route('posts.moderate-bulk', $thread), [
            'post_ids' => $posts->pluck('id')->all(),
            'action' => 'delete',
            'reason' => 'Cleanup',
        ]);

        Queue::assertPushed(NotifyPostAuthorOfModerationJob::class, 3);
    }

    public function test_the_job_is_dispatched_after_commit(): void
    {
        $this->markTestSkipped('Telling a post author that a moderator acted arrives with the notifications slice: S6 (a cutover requirement).');

        // Every caller that creates a PostModerationLog does so inside
        // DB::transaction(), and the queue connections here all default to
        // after_commit=false. Without ShouldQueueAfterCommit, a worker fast
        // enough to pick the job up before that transaction commits would
        // find no row (PostModerationLog::find() returns null on a separate
        // connection) and silently return without notifying the author.
        $this->assertInstanceOf(ShouldQueueAfterCommit::class, new NotifyPostAuthorOfModerationJob(1));
    }

    public function test_the_job_notifies_the_authors_bell_and_skips_the_moderators_own_post(): void
    {
        $this->markTestSkipped('Telling a post author that a moderator acted arrives with the notifications slice: S6 (a cutover requirement).');

        Notification::fake();
        [$thread, $post] = $this->threadWithReply();
        $moderator = $this->moderator();

        $log = PostModerationLog::query()->create([
            'thread_id' => $thread->id,
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'action' => 'delete',
            'reason' => 'Spam',
        ]);

        (new NotifyPostAuthorOfModerationJob($log->id))->handle();

        Notification::assertSentTo(
            $post->author,
            PostModeratedNotification::class,
            fn ($notification, array $channels): bool => in_array('database', $channels, true),
        );
    }

    public function test_the_job_does_nothing_when_the_moderator_acted_on_their_own_post(): void
    {
        $this->markTestSkipped('Telling a post author that a moderator acted arrives with the notifications slice: S6 (a cutover requirement).');

        Notification::fake();
        $thread = $this->publicThread();
        $moderator = $this->moderator();
        $ownPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $moderator->id,
            'body' => 'own post',
        ]);

        $log = PostModerationLog::query()->create([
            'thread_id' => $thread->id,
            'post_id' => $ownPost->id,
            'moderator_id' => $moderator->id,
            'action' => 'delete',
            'reason' => null,
        ]);

        (new NotifyPostAuthorOfModerationJob($log->id))->handle();

        Notification::assertNotSentTo($moderator, PostModeratedNotification::class);
    }

    public function test_a_benign_action_never_queues_a_job(): void
    {
        $this->markTestSkipped('Telling a post author that a moderator acted arrives with the notifications slice: S6 (a cutover requirement).');

        Queue::fake();
        [$thread, $post] = $this->threadWithReply();
        $moderator = $this->moderator();

        PostModerationLog::query()->create([
            'thread_id' => $thread->id,
            'post_id' => $post->id,
            'moderator_id' => $moderator->id,
            'action' => 'approve',
            'reason' => null,
        ]);

        Queue::assertNotPushed(NotifyPostAuthorOfModerationJob::class);
    }

    private function jobTargetsLog(NotifyPostAuthorOfModerationJob $job, int $logId): bool
    {
        $reflected = new \ReflectionProperty($job, 'postModerationLogId');

        return $reflected->getValue($job) === $logId;
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }

    private function publicThread(): Thread
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Moderation Thread',
            'slug' => 'moderation-thread-'.Str::lower(Str::random(6)),
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        return $thread;
    }

    /**
     * @return array{0: Thread, 1: Post}
     */
    private function threadWithReply(): array
    {
        $thread = $this->publicThread();
        $reply = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => User::factory()->create()->id,
            'body' => 'Reply post',
        ]);

        return [$thread, $reply];
    }
}
