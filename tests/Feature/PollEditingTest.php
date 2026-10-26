<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Enums\PollStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Poll;
use Filagora\Forums\Models\PollOption;
use Filagora\Forums\Models\PollQuestion;
use Filagora\Forums\Models\PollVote;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PollEditingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_can_edit_a_poll_with_no_votes(): void
    {
        [$thread, $poll] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->put(route('polls.update', $poll), [
            'poll_questions' => [
                ['prompt' => 'Updated question?', 'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]],
            ],
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#poll');
        $response->assertSessionHas('thread_status', __('forum.flash.poll_updated'));

        $poll->refresh();
        $this->assertSame(1, $poll->questions()->count());
        $question = $poll->questions()->firstOrFail();
        $this->assertSame('Updated question?', $question->prompt);
        $this->assertSame(3, $question->options()->count());

        $this->assertDatabaseHas('thread_moderation_logs', [
            'thread_id' => $thread->id,
            'moderator_id' => $moderator->id,
            'action' => 'poll_updated',
        ]);
    }

    public function test_edit_form_is_prefilled_with_existing_questions_and_options(): void
    {
        [, $poll] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->get(route('polls.edit', $poll));

        $response->assertOk();
        $response->assertSee('value="Favourite colour?"', false);
        $response->assertSee('value="Red"', false);
        $response->assertSee('value="Blue"', false);
    }

    public function test_non_staff_cannot_edit_a_poll(): void
    {
        [, $poll] = $this->createThreadWithPoll();
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('polls.edit', $poll))->assertForbidden();

        $response = $this->actingAs($member)->put(route('polls.update', $poll), [
            'poll_questions' => [
                ['prompt' => 'Sneaky edit', 'options' => [['label' => 'A'], ['label' => 'B']]],
            ],
        ]);
        $response->assertForbidden();

        $poll->refresh();
        $this->assertSame('Favourite colour?', $poll->questions()->first()->prompt);
    }

    public function test_cannot_edit_a_poll_that_already_has_votes(): void
    {
        [$thread, $poll, $question] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();
        $voter = User::factory()->create();

        $vote = PollVote::query()->create(['poll_id' => $poll->id, 'user_id' => $voter->id]);
        $vote->answers()->create([
            'poll_question_id' => $question->id,
            'poll_option_id' => $question->options()->first()->id,
        ]);

        $editForm = $this->actingAs($moderator)->get(route('polls.edit', $poll));
        $editForm->assertRedirect(route('threads.show', $thread).'#poll');
        $editForm->assertSessionHas('thread_status', __('forum.errors.poll_has_votes'));

        $update = $this->actingAs($moderator)->put(route('polls.update', $poll), [
            'poll_questions' => [
                ['prompt' => 'Should not apply', 'options' => [['label' => 'A'], ['label' => 'B']]],
            ],
        ]);
        $update->assertRedirect(route('threads.show', $thread).'#poll');
        $update->assertSessionHas('thread_status', __('forum.errors.poll_has_votes'));

        $poll->refresh();
        $this->assertSame('Favourite colour?', $poll->questions()->first()->prompt);
    }

    public function test_editing_a_poll_validates_minimum_two_options_per_question(): void
    {
        [, $poll] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->put(route('polls.update', $poll), [
            'poll_questions' => [
                ['prompt' => 'Only one option', 'options' => [['label' => 'Solo']]],
            ],
        ]);

        $response->assertSessionHasErrors('poll_questions.0.options');
        $poll->refresh();
        $this->assertSame('Favourite colour?', $poll->questions()->first()->prompt);
    }

    public function test_edit_poll_link_is_hidden_once_the_poll_has_votes(): void
    {
        [$thread, $poll, $question] = $this->createThreadWithPoll();
        $moderator = $this->createModerator();
        $voter = User::factory()->create();

        $this->actingAs($moderator)->get(route('threads.show', $thread))->assertSee('Edit Poll');

        $vote = PollVote::query()->create(['poll_id' => $poll->id, 'user_id' => $voter->id]);
        $vote->answers()->create([
            'poll_question_id' => $question->id,
            'poll_option_id' => $question->options()->first()->id,
        ]);

        $this->actingAs($moderator)->get(route('threads.show', $thread))->assertDontSee('Edit Poll');
    }

    /**
     * @return array{0: Thread, 1: Poll, 2: PollQuestion}
     */
    private function createThreadWithPoll(): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-polledit-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-polledit-'.Str::lower(Str::random(8)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Poll Editing Test Thread',
            'slug' => 'poll-editing-test-thread-'.Str::lower(Str::random(8)),
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

        $question = PollQuestion::query()->create([
            'poll_id' => $poll->id,
            'prompt' => 'Favourite colour?',
            'is_multi_select' => false,
            'sort_order' => 0,
        ]);
        PollOption::query()->create(['poll_question_id' => $question->id, 'label' => 'Red', 'sort_order' => 0]);
        PollOption::query()->create(['poll_question_id' => $question->id, 'label' => 'Blue', 'sort_order' => 1]);

        return [$thread, $poll, $question];
    }

    private function createModerator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
