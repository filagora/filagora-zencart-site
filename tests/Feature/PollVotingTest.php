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

class PollVotingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_authenticated_user_can_vote_in_a_poll(): void
    {
        [$thread, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();

        $singleOptionId = $questions['single']->options->first()->id;
        $multiOptionIds = $questions['multi']->options->pluck('id')->take(2)->all();

        $response = $this->actingAs($user)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => [$singleOptionId],
                $questions['multi']->id => $multiOptionIds,
            ],
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#poll');
        $response->assertSessionHas('thread_status', __('forum.flash.poll_voted'));

        $this->assertDatabaseHas('poll_votes', ['poll_id' => $poll->id, 'user_id' => $user->id]);
        $vote = PollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(3, $vote->answers()->count());
    }

    public function test_guest_cannot_vote(): void
    {
        [, $poll] = $this->createThreadWithPoll();

        $response = $this->post(route('polls.vote', $poll), []);

        $response->assertRedirect('/?auth=login&intended=%2Fthreads%2F'.$poll->thread_id);
        $this->assertSame(0, PollVote::query()->count());
    }

    public function test_vote_requires_an_answer_to_every_question(): void
    {
        [, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();

        $singleOptionId = $questions['single']->options->first()->id;

        $response = $this->actingAs($user)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => [$singleOptionId],
                // multi-select question omitted entirely.
            ],
        ]);

        $response->assertSessionHasErrors("poll_answers.{$questions['multi']->id}");
        $this->assertSame(0, PollVote::query()->count());
    }

    public function test_single_select_question_rejects_multiple_selected_options(): void
    {
        [, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();

        $optionIds = $questions['single']->options->pluck('id')->all();

        $response = $this->actingAs($user)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => $optionIds,
                $questions['multi']->id => [$questions['multi']->options->first()->id],
            ],
        ]);

        $response->assertSessionHasErrors("poll_answers.{$questions['single']->id}");
        $this->assertSame(0, PollVote::query()->count());
    }

    public function test_multi_select_question_accepts_multiple_selected_options(): void
    {
        [, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();

        $multiOptionIds = $questions['multi']->options->pluck('id')->all();

        $this->actingAs($user)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => [$questions['single']->options->first()->id],
                $questions['multi']->id => $multiOptionIds,
            ],
        ])->assertRedirect();

        $vote = PollVote::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(count($multiOptionIds) + 1, $vote->answers()->count());
    }

    public function test_vote_rejects_an_option_id_that_belongs_to_a_different_question(): void
    {
        [, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();

        $wrongOptionId = $questions['multi']->options->first()->id;

        $response = $this->actingAs($user)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => [$wrongOptionId],
                $questions['multi']->id => [$questions['multi']->options->first()->id],
            ],
        ]);

        $response->assertSessionHasErrors("poll_answers.{$questions['single']->id}.0");
        $this->assertSame(0, PollVote::query()->count());
    }

    public function test_user_cannot_vote_twice_in_the_same_poll(): void
    {
        [$thread, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();

        $payload = [
            'poll_answers' => [
                $questions['single']->id => [$questions['single']->options->first()->id],
                $questions['multi']->id => [$questions['multi']->options->first()->id],
            ],
        ];

        $this->actingAs($user)->post(route('polls.vote', $poll), $payload)->assertRedirect();
        $this->assertSame(1, PollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id)->count());

        $second = $this->actingAs($user)->post(route('polls.vote', $poll), $payload);
        $second->assertSessionHas('thread_status', __('forum.errors.poll_already_voted'));
        $this->assertSame(1, PollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id)->count());
    }

    public function test_double_submitting_a_vote_does_not_create_two_vote_records(): void
    {
        [, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();

        $payload = [
            'poll_answers' => [
                $questions['single']->id => [$questions['single']->options->first()->id],
                $questions['multi']->id => [$questions['multi']->options->first()->id],
            ],
        ];

        // Simulate a double-click/back-button resubmit: two rapid, identical
        // POSTs of the same vote.
        $this->actingAs($user)->post(route('polls.vote', $poll), $payload);
        $this->actingAs($user)->post(route('polls.vote', $poll), $payload);

        $this->assertSame(1, PollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id)->count());
    }

    public function test_cannot_vote_in_a_closed_poll(): void
    {
        [, $poll, $questions] = $this->createThreadWithPoll();
        $poll->update(['status' => PollStatus::CLOSED, 'closed_at' => now()]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => [$questions['single']->options->first()->id],
                $questions['multi']->id => [$questions['multi']->options->first()->id],
            ],
        ]);

        $response->assertSessionHas('thread_status', __('forum.errors.poll_closed'));
        $this->assertSame(0, PollVote::query()->count());
    }

    public function test_poll_results_are_hidden_until_the_user_votes(): void
    {
        [$thread, $poll, $questions] = $this->createThreadWithPoll();
        $user = User::factory()->create();
        $optionLabel = $questions['single']->options->first()->label;

        $before = $this->actingAs($user)->get(route('threads.show', $thread));
        $before->assertOk();
        $before->assertDontSee('data-poll-results-marker', false);

        $this->actingAs($user)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => [$questions['single']->options->first()->id],
                $questions['multi']->id => [$questions['multi']->options->first()->id],
            ],
        ]);

        $after = $this->actingAs($user)->get(route('threads.show', $thread));
        $after->assertOk();
        $after->assertSee($optionLabel);
    }

    public function test_staff_can_see_poll_results_without_voting(): void
    {
        [$thread, $poll, $questions] = $this->createThreadWithPoll();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $response = $this->actingAs($moderator)->get(route('threads.show', $thread));

        $response->assertOk();
        $response->assertDontSee('Log in to vote');
        // Seeing results early doesn't stop staff from also voting: they get
        // both the results view and the voting form until they actually vote.
        $response->assertSee('Cast your vote');
        $response->assertSee('name="poll_answers['.$questions['single']->id.'][]"', false);
    }

    public function test_staff_can_vote_on_a_poll_they_created(): void
    {
        [$thread, $poll, $questions] = $this->createThreadWithPoll();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');
        $poll->update(['created_by_id' => $moderator->id]);

        $response = $this->actingAs($moderator)->post(route('polls.vote', $poll), [
            'poll_answers' => [
                $questions['single']->id => [$questions['single']->options->first()->id],
                $questions['multi']->id => [$questions['multi']->options->first()->id],
            ],
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#poll');
        $response->assertSessionHas('thread_status', __('forum.flash.poll_voted'));
        $this->assertDatabaseHas('poll_votes', ['poll_id' => $poll->id, 'user_id' => $moderator->id]);
    }

    /**
     * @return array{0: Thread, 1: Poll, 2: array{single: PollQuestion, multi: PollQuestion}}
     */
    private function createThreadWithPoll(): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-pollvote-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-pollvote-'.Str::lower(Str::random(8)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Poll Voting Test Thread',
            'slug' => 'poll-voting-test-thread-'.Str::lower(Str::random(8)),
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

        $singleQuestion = PollQuestion::query()->create([
            'poll_id' => $poll->id,
            'prompt' => 'Favourite colour?',
            'is_multi_select' => false,
            'sort_order' => 0,
        ]);
        PollOption::query()->create(['poll_question_id' => $singleQuestion->id, 'label' => 'Red', 'sort_order' => 0]);
        PollOption::query()->create(['poll_question_id' => $singleQuestion->id, 'label' => 'Blue', 'sort_order' => 1]);

        $multiQuestion = PollQuestion::query()->create([
            'poll_id' => $poll->id,
            'prompt' => 'Pick your tools',
            'is_multi_select' => true,
            'sort_order' => 1,
        ]);
        PollOption::query()->create(['poll_question_id' => $multiQuestion->id, 'label' => 'Hammer', 'sort_order' => 0]);
        PollOption::query()->create(['poll_question_id' => $multiQuestion->id, 'label' => 'Wrench', 'sort_order' => 1]);
        PollOption::query()->create(['poll_question_id' => $multiQuestion->id, 'label' => 'Saw', 'sort_order' => 2]);

        $singleQuestion->load('options');
        $multiQuestion->load('options');

        return [$thread, $poll, ['single' => $singleQuestion, 'multi' => $multiQuestion]];
    }
}
