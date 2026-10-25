<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Enums\PollStatus;
use Filagora\Forums\Enums\PostReportStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Poll;
use Filagora\Forums\Models\PostModerationLog;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\ThreadModerationLog;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\PostAttachment;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class RelocateActionsTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_bulk_move_relocates_selected_replies_to_one_thread(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 3);
        [$destination] = $this->threadWithReplies($forum, 0);
        $move = [$posts[0]->id, $posts[1]->id];

        $json = $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate-bulk', $source), [
                'action' => 'move',
                'post_ids' => $move,
                'destination_thread_id' => $destination->id,
            ])->assertOk()->json();

        $this->assertSame(2, $json['count']);
        $this->assertNotNull($json['undo_token']);
        foreach ($move as $id) {
            $this->assertSame($destination->id, (int) Post::query()->find($id)->thread_id);
        }
        $this->assertSame($source->id, (int) Post::query()->find($posts[2]->id)->thread_id);
        $this->assertDatabaseHas('post_moderation_logs', ['post_id' => $move[0], 'action' => 'move']);
    }

    public function test_bulk_promote_makes_one_new_thread_with_every_selected_reply(): void
    {
        $forum = $this->forum();
        $destForum = $this->forum('Bugs');
        [$source, $posts] = $this->threadWithReplies($forum, 3);

        $json = $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate-bulk', $source), [
                'action' => 'promote',
                'post_ids' => [$posts[0]->id, $posts[1]->id],
                'destination_forum_id' => $destForum->id,
                'new_thread_title' => 'Split: checkout cache bug',
            ])->assertOk()->json();

        $newThreadId = (int) Post::query()->find($posts[0]->id)->thread_id;
        $this->assertNotSame($source->id, $newThreadId);
        $this->assertSame($newThreadId, (int) Post::query()->find($posts[1]->id)->thread_id);
        $newThread = Thread::query()->find($newThreadId);
        $this->assertSame($destForum->id, (int) $newThread->forum_id);
        $this->assertSame('Split: checkout cache bug', $newThread->title);
        $this->assertNotNull($json['undo_token']);
    }

    public function test_bulk_delete_soft_deletes_selected_replies(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 3);

        $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate-bulk', $source), [
                'action' => 'delete',
                'post_ids' => [$posts[0]->id, $posts[1]->id],
                'reason' => 'spam run',
            ])->assertOk();

        $this->assertTrue((bool) Post::query()->find($posts[0]->id)->is_deleted);
        $this->assertTrue((bool) Post::query()->find($posts[1]->id)->is_deleted);
        $this->assertFalse((bool) Post::query()->find($posts[2]->id)->is_deleted);
    }

    public function test_bulk_move_can_include_the_thread_starter_and_removes_the_emptied_source(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 2);
        $starter = Post::query()->where('thread_id', $source->id)->orderBy('id')->first();
        [$destination] = $this->threadWithReplies($forum, 0);

        $mod = $this->moderator();
        $json = $this->actingAs($mod)
            ->postJson(route('posts.moderate-bulk', $source), [
                'action' => 'move',
                'post_ids' => [$starter->id, $posts[0]->id, $posts[1]->id],
                'destination_thread_id' => $destination->id,
            ])->assertOk()->json();

        $this->assertSame($destination->id, (int) Post::query()->find($starter->id)->thread_id);
        $this->assertNull(Thread::query()->find($source->id), 'the fully-emptied source is gone');
        $this->assertTrue($json['navigate_away']);

        $this->actingAs($mod)
            ->postJson(route('moderation.relocate.undo'), ['token' => $json['undo_token']])
            ->assertOk();

        $this->assertNotNull(Thread::query()->find($source->id), 'undo brings the source back');
        $this->assertSame($source->id, (int) Post::query()->find($starter->id)->thread_id);
    }

    public function test_bulk_rejects_posts_that_are_not_in_the_thread(): void
    {
        $forum = $this->forum();
        [$source, $sourcePosts] = $this->threadWithReplies($forum, 2);
        [, $otherPosts] = $this->threadWithReplies($forum, 2);
        [$destination] = $this->threadWithReplies($forum, 0);

        $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate-bulk', $source), [
                'action' => 'move',
                'post_ids' => [$sourcePosts[0]->id, $otherPosts[0]->id],
                'destination_thread_id' => $destination->id,
            ])->assertStatus(422);
    }

    public function test_bulk_is_staff_only(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 2);
        [$destination] = $this->threadWithReplies($forum, 0);

        $this->actingAs(User::factory()->create())
            ->postJson(route('posts.moderate-bulk', $source), [
                'action' => 'move',
                'post_ids' => [$posts[0]->id],
                'destination_thread_id' => $destination->id,
            ])->assertForbidden();
    }

    public function test_undo_reverses_a_thread_move(): void
    {
        $forum = $this->forum('Alpha');
        $dest = $this->forum('Bravo');
        [$thread] = $this->threadWithReplies($forum, 1);
        $mod = $this->moderator();

        $token = $this->actingAs($mod)->postJson(route('threads.moderate', $thread), [
            'action' => 'move',
            'forum_id' => $dest->id,
        ])->assertOk()->json('undo_token');

        $this->assertSame($dest->id, (int) $thread->fresh()->forum_id);

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();
        $this->assertSame($forum->id, (int) $thread->fresh()->forum_id);
    }

    public function test_undo_reverses_a_post_move(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 2);
        [$destination] = $this->threadWithReplies($forum, 0);
        $mod = $this->moderator();

        $token = $this->actingAs($mod)->postJson(route('posts.moderate', $posts[0]), [
            'action' => 'move',
            'destination_thread_id' => $destination->id,
        ])->assertOk()->json('undo_token');

        $this->assertSame($destination->id, (int) $posts[0]->fresh()->thread_id);

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();
        $this->assertSame($source->id, (int) $posts[0]->fresh()->thread_id);
    }

    public function test_undo_reverses_a_promote_and_removes_the_emptied_thread(): void
    {
        $forum = $this->forum();
        $destForum = $this->forum('Bugs');
        [$source, $posts] = $this->threadWithReplies($forum, 2);
        $mod = $this->moderator();

        $token = $this->actingAs($mod)->postJson(route('posts.moderate', $posts[0]), [
            'action' => 'promote',
            'destination_forum_id' => $destForum->id,
            'new_thread_title' => 'Promoted reply',
        ])->assertOk()->json('undo_token');

        $newThreadId = (int) $posts[0]->fresh()->thread_id;
        $this->assertNotSame($source->id, $newThreadId);

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();

        $this->assertSame($source->id, (int) $posts[0]->fresh()->thread_id);
        $this->assertNull(Thread::query()->find($newThreadId), 'the emptied promoted thread is gone');
    }

    public function test_moving_the_only_post_out_removes_the_emptied_source_thread(): void
    {
        $forum = $this->forum();
        [$source] = $this->threadWithReplies($forum, 0);
        [$destination] = $this->threadWithReplies($forum, 0);
        $starter = Post::query()->where('thread_id', $source->id)->firstOrFail();

        $json = $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate', $starter), [
                'action' => 'move',
                'destination_thread_id' => $destination->id,
            ])->assertOk()->json();

        $this->assertSame($destination->id, (int) $starter->fresh()->thread_id);
        $this->assertNull(Thread::query()->find($source->id), 'the emptied source thread is gone');
        $this->assertTrue($json['navigate_away']);
    }

    public function test_moving_the_only_post_keeps_a_source_thread_that_has_a_poll(): void
    {
        $forum = $this->forum();
        [$source] = $this->threadWithReplies($forum, 0);
        [$destination] = $this->threadWithReplies($forum, 0);
        $starter = Post::query()->where('thread_id', $source->id)->firstOrFail();
        Poll::query()->create(['thread_id' => $source->id, 'status' => PollStatus::OPEN->value]);

        $json = $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate', $starter), [
                'action' => 'move',
                'destination_thread_id' => $destination->id,
            ])->assertOk()->json();

        $this->assertSame($destination->id, (int) $starter->fresh()->thread_id);
        $this->assertNotNull(Thread::query()->find($source->id), 'a thread with a poll is kept');
        $this->assertFalse($json['navigate_away']);
    }

    public function test_undo_of_an_emptying_move_recreates_the_removed_source_thread(): void
    {
        $forum = $this->forum();
        [$source] = $this->threadWithReplies($forum, 0);
        [$destination] = $this->threadWithReplies($forum, 0);
        $starter = Post::query()->where('thread_id', $source->id)->firstOrFail();
        $mod = $this->moderator();
        $sourceId = $source->id;
        $sourceTitle = $source->title;

        $token = $this->actingAs($mod)->postJson(route('posts.moderate', $starter), [
            'action' => 'move',
            'destination_thread_id' => $destination->id,
        ])->assertOk()->json('undo_token');

        $this->assertNull(Thread::query()->find($sourceId));

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();

        $restored = Thread::query()->find($sourceId);
        $this->assertNotNull($restored, 'the source thread is back');
        $this->assertSame($sourceTitle, $restored->title);
        $this->assertSame($sourceId, (int) $starter->fresh()->thread_id);
    }

    public function test_promoting_the_only_post_removes_the_emptied_source_thread(): void
    {
        $forum = $this->forum();
        $destForum = $this->forum('Bugs');
        [$source] = $this->threadWithReplies($forum, 0);
        $starter = Post::query()->where('thread_id', $source->id)->firstOrFail();

        $json = $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate', $starter), [
                'action' => 'promote',
                'destination_forum_id' => $destForum->id,
                'new_thread_title' => 'Lifted out',
            ])->assertOk()->json();

        $newThreadId = (int) $starter->fresh()->thread_id;
        $this->assertNotSame($source->id, $newThreadId);
        $this->assertNull(Thread::query()->find($source->id), 'the emptied source thread is gone');
        $this->assertTrue($json['navigate_away']);
    }

    public function test_undo_of_a_starter_promote_recreates_source_and_drops_the_new_thread(): void
    {
        $forum = $this->forum();
        $destForum = $this->forum('Bugs');
        [$source] = $this->threadWithReplies($forum, 0);
        $starter = Post::query()->where('thread_id', $source->id)->firstOrFail();
        $mod = $this->moderator();
        $sourceId = $source->id;

        $token = $this->actingAs($mod)->postJson(route('posts.moderate', $starter), [
            'action' => 'promote',
            'destination_forum_id' => $destForum->id,
            'new_thread_title' => 'Lifted out',
        ])->assertOk()->json('undo_token');

        $newThreadId = (int) $starter->fresh()->thread_id;
        $this->assertNull(Thread::query()->find($sourceId));

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();

        $this->assertNotNull(Thread::query()->find($sourceId), 'the source thread is back');
        $this->assertSame($sourceId, (int) $starter->fresh()->thread_id);
        $this->assertNull(Thread::query()->find($newThreadId), 'the emptied promote thread is gone');
    }

    public function test_disposing_the_source_carries_over_its_logs_and_reports_and_undo_restores_subscribers(): void
    {
        $forum = $this->forum();
        [$source] = $this->threadWithReplies($forum, 0);
        [$destination] = $this->threadWithReplies($forum, 0);
        $starter = Post::query()->where('thread_id', $source->id)->firstOrFail();
        $mod = $this->moderator();

        // Pre-existing history on the source: a post log, an open report, a
        // thread-level log, and two subscribers.
        PostModerationLog::query()->create([
            'thread_id' => $source->id, 'post_id' => $starter->id,
            'moderator_id' => $mod->id, 'action' => 'approve', 'reason' => null,
        ]);
        PostReport::query()->create([
            'post_id' => $starter->id, 'thread_id' => $source->id,
            'reporter_id' => User::factory()->create()->id, 'reason' => 'spam',
            'status' => PostReportStatus::OPEN->value,
        ]);
        ThreadModerationLog::query()->create([
            'thread_id' => $source->id, 'moderator_id' => $mod->id, 'action' => 'move', 'reason' => 'earlier move',
        ]);
        $subA = User::factory()->create();
        $subB = User::factory()->create();
        $source->subscribedUsers()->attach([$subA->id, $subB->id]);

        $token = $this->actingAs($mod)->postJson(route('posts.moderate', $starter), [
            'action' => 'move',
            'destination_thread_id' => $destination->id,
        ])->assertOk()->json('undo_token');

        $this->assertNull(Thread::query()->find($source->id));
        // logs + report followed the post to the destination rather than being destroyed
        $this->assertDatabaseHas('post_moderation_logs', ['post_id' => $starter->id, 'action' => 'approve', 'thread_id' => $destination->id]);
        $this->assertDatabaseHas('post_reports', ['post_id' => $starter->id, 'thread_id' => $destination->id]);

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();

        $restored = Thread::query()->find($source->id);
        $this->assertNotNull($restored);
        $this->assertEqualsCanonicalizing(
            [$subA->id, $subB->id],
            $restored->subscribedUsers()->pluck('users.id')->all(),
            'subscribers are restored on undo'
        );
        // logs + report come back to the source with the post
        $this->assertDatabaseHas('post_moderation_logs', ['post_id' => $starter->id, 'action' => 'approve', 'thread_id' => $source->id]);
        $this->assertDatabaseHas('post_reports', ['post_id' => $starter->id, 'thread_id' => $source->id]);
        $this->assertDatabaseHas('thread_moderation_logs', ['thread_id' => $source->id, 'action' => 'move', 'reason' => 'earlier move']);
    }

    public function test_moving_a_post_carries_its_attachment_thread_pointer_and_undo_restores_it(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 2);
        [$destination] = $this->threadWithReplies($forum, 0);
        $mod = $this->moderator();

        $attachment = PostAttachment::query()->create([
            'legacy_attachment_id' => random_int(100000, 999999),
            'post_id' => $posts[0]->id,
            'thread_id' => $source->id,
            'original_filename' => 'x.png',
            'mime_type' => 'image/png',
            'storage_disk' => 'local',
            'storage_path' => 'attachments/x.png',
        ]);

        $token = $this->actingAs($mod)->postJson(route('posts.moderate', $posts[0]), [
            'action' => 'move',
            'destination_thread_id' => $destination->id,
        ])->assertOk()->json('undo_token');

        $this->assertSame($destination->id, (int) $attachment->fresh()->thread_id);

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();
        $this->assertSame($source->id, (int) $attachment->fresh()->thread_id);
    }

    public function test_a_second_moderator_cannot_burn_the_movers_undo_token(): void
    {
        $forum = $this->forum('Alpha');
        $dest = $this->forum('Bravo');
        [$thread] = $this->threadWithReplies($forum, 1);
        $mover = $this->moderator();
        $other = $this->moderator();

        $token = $this->actingAs($mover)->postJson(route('threads.moderate', $thread), [
            'action' => 'move', 'forum_id' => $dest->id,
        ])->assertOk()->json('undo_token');

        // Someone else fumbles the token — must not consume it.
        $this->actingAs($other)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertStatus(410);

        // The real mover can still undo.
        $this->actingAs($mover)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();
        $this->assertSame($forum->id, (int) $thread->fresh()->forum_id);
    }

    public function test_bulk_move_disposes_only_the_fully_emptied_source_thread(): void
    {
        $forum = $this->forum();
        [$emptied, $emptiedPosts] = $this->threadWithReplies($forum, 1);
        $emptiedStarter = Post::query()->where('thread_id', $emptied->id)->orderBy('id')->firstOrFail();
        [$kept, $keptPosts] = $this->threadWithReplies($forum, 2);
        [$destination] = $this->threadWithReplies($forum, 0);
        $mod = $this->moderator();

        // Move every post of $emptied, but only one reply of $kept.
        $this->actingAs($mod)->postJson(route('posts.moderate-bulk', $emptied), [
            'action' => 'move',
            'post_ids' => [$emptiedStarter->id, $emptiedPosts[0]->id],
            'destination_thread_id' => $destination->id,
        ])->assertOk();

        $this->assertNull(Thread::query()->find($emptied->id));

        // A separate call for the still-populated thread.
        $this->actingAs($mod)->postJson(route('posts.moderate-bulk', $kept), [
            'action' => 'move',
            'post_ids' => [$keptPosts[0]->id],
            'destination_thread_id' => $destination->id,
        ])->assertOk();

        $this->assertNotNull(Thread::query()->find($kept->id), 'a thread that still has posts is kept');
    }

    public function test_bulk_move_rejects_destination_equal_to_source(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 2);

        $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate-bulk', $source), [
                'action' => 'move',
                'post_ids' => [$posts[0]->id],
                'destination_thread_id' => $source->id,
            ])->assertStatus(422);
    }

    public function test_promote_rejects_a_too_short_title(): void
    {
        $forum = $this->forum();
        $destForum = $this->forum('Bugs');
        [, $posts] = $this->threadWithReplies($forum, 1);

        $this->actingAs($this->moderator())
            ->postJson(route('posts.moderate', $posts[0]), [
                'action' => 'promote',
                'destination_forum_id' => $destForum->id,
                'new_thread_title' => '  ab  ',
            ])->assertStatus(422);
    }

    public function test_return_to_open_redirect_is_ignored(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 4);
        [$destination] = $this->threadWithReplies($forum, 0);
        $mod = $this->moderator();

        $hostile = ['//evil.example.com/phish', "/\tevil.example.com", '/\\evil.example.com', "/ok\nSet-Cookie: x=1"];
        foreach ($hostile as $i => $returnTo) {
            $response = $this->actingAs($mod)->post(route('posts.moderate', $posts[$i]), [
                'action' => 'move',
                'destination_thread_id' => $destination->id,
                'return_to' => $returnTo,
            ]);

            $response->assertRedirect();
            $this->assertStringNotContainsString('evil.example.com', (string) $response->headers->get('Location'), "hostile return_to: {$returnTo}");
        }
    }

    public function test_undo_reverses_a_bulk_move(): void
    {
        $forum = $this->forum();
        [$source, $posts] = $this->threadWithReplies($forum, 3);
        [$destination] = $this->threadWithReplies($forum, 0);
        $mod = $this->moderator();

        $token = $this->actingAs($mod)->postJson(route('posts.moderate-bulk', $source), [
            'action' => 'move',
            'post_ids' => [$posts[0]->id, $posts[1]->id],
            'destination_thread_id' => $destination->id,
        ])->assertOk()->json('undo_token');

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();

        $this->assertSame($source->id, (int) $posts[0]->fresh()->thread_id);
        $this->assertSame($source->id, (int) $posts[1]->fresh()->thread_id);
    }

    public function test_undo_token_is_single_use(): void
    {
        $forum = $this->forum('Alpha');
        $dest = $this->forum('Bravo');
        [$thread] = $this->threadWithReplies($forum, 1);
        $mod = $this->moderator();

        $token = $this->actingAs($mod)->postJson(route('threads.moderate', $thread), [
            'action' => 'move', 'forum_id' => $dest->id,
        ])->json('undo_token');

        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertOk();
        $this->actingAs($mod)->postJson(route('moderation.relocate.undo'), ['token' => $token])->assertStatus(410);
    }

    public function test_undo_rejects_a_token_from_another_moderator(): void
    {
        $forum = $this->forum('Alpha');
        $dest = $this->forum('Bravo');
        [$thread] = $this->threadWithReplies($forum, 1);

        $token = $this->actingAs($this->moderator())->postJson(route('threads.moderate', $thread), [
            'action' => 'move', 'forum_id' => $dest->id,
        ])->json('undo_token');

        $this->actingAs($this->moderator())
            ->postJson(route('moderation.relocate.undo'), ['token' => $token])
            ->assertStatus(410);
        $this->assertSame($dest->id, (int) $thread->fresh()->forum_id);
    }

    private function forum(string $name = 'General Support', string $groupName = 'General'): Forum
    {
        $group = ForumGroup::query()->create([
            'name' => $groupName,
            'slug' => Str::slug($groupName).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: Thread, 1: array<int, Post>} the thread and its non-starter replies
     */
    private function threadWithReplies(Forum $forum, int $replyCount): array
    {
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread '.Str::random(6),
            'slug' => 'thread-'.Str::lower(Str::random(8)),
            'reply_count' => 0,
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
            'moderation_status' => ContentModerationStatus::APPROVED->value,
        ]);

        $replies = [];
        for ($i = 0; $i < $replyCount; $i++) {
            $replies[] = Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => User::factory()->create()->id,
                'body' => "Reply {$i}",
                'moderation_status' => ContentModerationStatus::APPROVED->value,
            ]);
        }

        return [$thread, $replies];
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
