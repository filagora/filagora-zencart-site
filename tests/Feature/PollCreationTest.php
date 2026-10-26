<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Enums\PollStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Poll;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PollCreationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_can_add_a_poll_to_an_existing_thread(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->post(route('threads.poll.store', $thread), [
            'poll_questions' => [
                ['prompt' => 'Favourite colour?', 'options' => [['label' => 'Red'], ['label' => 'Blue']]],
                ['prompt' => 'Pick your tools', 'is_multi_select' => '1', 'options' => [['label' => 'Hammer'], ['label' => 'Wrench'], ['label' => 'Saw']]],
            ],
        ]);

        $response->assertRedirect(route('threads.show', $thread).'#poll');
        $response->assertSessionHas('thread_status', __('forum.flash.poll_created'));

        $poll = Poll::query()->where('thread_id', $thread->id)->firstOrFail();
        $this->assertSame(PollStatus::OPEN, $poll->status);
        $this->assertSame($moderator->id, $poll->created_by_id);
        $this->assertSame(2, $poll->questions()->count());

        $firstQuestion = $poll->questions()->orderBy('sort_order')->first();
        $this->assertSame('Favourite colour?', $firstQuestion->prompt);
        $this->assertFalse($firstQuestion->is_multi_select);
        $this->assertSame(2, $firstQuestion->options()->count());

        $secondQuestion = $poll->questions()->orderBy('sort_order')->skip(1)->first();
        $this->assertTrue($secondQuestion->is_multi_select);
        $this->assertSame(3, $secondQuestion->options()->count());
    }

    public function test_staff_can_create_a_thread_with_a_poll_in_one_step(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->post(route('threads.create', $thread->forum), [
            'title' => 'Thread With Poll',
            'body' => 'Opening post body.',
            'create_poll' => '1',
            'poll_questions' => [
                ['prompt' => 'Ship it?', 'options' => [['label' => 'Yes'], ['label' => 'No']]],
            ],
        ]);

        $createdThread = Thread::query()->where('title', 'Thread With Poll')->firstOrFail();
        $response->assertSessionHas('thread_status');

        $poll = Poll::query()->where('thread_id', $createdThread->id)->first();
        $this->assertNotNull($poll);
        $this->assertSame(1, $poll->questions()->count());
    }

    public function test_new_thread_form_ships_poll_fields_disabled_so_a_plain_thread_can_submit(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        // Poll inputs carry `required`; while the poll section is hidden a
        // required empty field otherwise blocks the whole thread form on
        // submit ("invalid form control ... is not focusable"). They must
        // render disabled until the "add a poll" box is checked.
        $response = $this->actingAs($moderator)->get(route('threads.create.form', $thread->forum));

        $response->assertOk();
        $response->assertSee('data-poll-toggle-fields', false);
        $response->assertSee('name="poll_questions[0][prompt]"', false);
        $this->assertMatchesRegularExpression(
            '/name="poll_questions\[0\]\[prompt\]"[^>]*\sdisabled/s',
            $response->getContent(),
        );
    }

    public function test_dedicated_poll_form_does_not_disable_its_fields(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->get(route('threads.poll.create', $thread));

        $response->assertOk();
        $response->assertSee('name="poll_questions[0][prompt]"', false);
        $this->assertDoesNotMatchRegularExpression(
            '/name="poll_questions\[0\]\[prompt\]"[^>]*\sdisabled/s',
            $response->getContent(),
        );
    }

    public function test_non_staff_cannot_add_a_poll_to_a_thread(): void
    {
        $thread = $this->createPublicThread();
        $member = User::factory()->create();

        $response = $this->actingAs($member)->post(route('threads.poll.store', $thread), [
            'poll_questions' => [
                ['prompt' => 'Attempted poll', 'options' => [['label' => 'A'], ['label' => 'B']]],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Poll::query()->where('thread_id', $thread->id)->count());
    }

    public function test_non_staff_create_poll_checkbox_is_ignored_even_if_submitted(): void
    {
        $thread = $this->createPublicThread();
        $member = User::factory()->create();

        $response = $this->actingAs($member)->post(route('threads.create', $thread->forum), [
            'title' => 'Member Thread',
            'body' => 'Member body.',
            'create_poll' => '1',
            'poll_questions' => [
                ['prompt' => 'Sneaky poll', 'options' => [['label' => 'A'], ['label' => 'B']]],
            ],
        ]);

        $createdThread = Thread::query()->where('title', 'Member Thread')->first();
        $this->assertNotNull($createdThread);
        $response->assertRedirect();
        $this->assertSame(0, Poll::query()->where('thread_id', $createdThread->id)->count());
    }

    public function test_cannot_add_a_second_poll_to_a_thread_that_already_has_one(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        Poll::query()->create([
            'thread_id' => $thread->id,
            'created_by_id' => $moderator->id,
            'status' => PollStatus::OPEN,
        ]);

        $response = $this->actingAs($moderator)->post(route('threads.poll.store', $thread), [
            'poll_questions' => [
                ['prompt' => 'Second poll?', 'options' => [['label' => 'A'], ['label' => 'B']]],
            ],
        ]);

        $response->assertRedirect(route('threads.show', $thread));
        $response->assertSessionHas('thread_status', __('forum.errors.poll_already_exists'));
        $this->assertSame(1, Poll::query()->where('thread_id', $thread->id)->count());
    }

    public function test_poll_creation_validates_at_least_one_question(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->post(route('threads.poll.store', $thread), [
            'poll_questions' => [],
        ]);

        $response->assertSessionHasErrors('poll_questions');
        $this->assertSame(0, Poll::query()->where('thread_id', $thread->id)->count());
    }

    public function test_poll_creation_validates_minimum_two_options_per_question(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->post(route('threads.poll.store', $thread), [
            'poll_questions' => [
                ['prompt' => 'Only one option', 'options' => [['label' => 'Solo']]],
            ],
        ]);

        $response->assertSessionHasErrors('poll_questions.0.options');
        $this->assertSame(0, Poll::query()->where('thread_id', $thread->id)->count());
    }

    public function test_poll_creation_is_logged_to_thread_moderation_log(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $this->actingAs($moderator)->post(route('threads.poll.store', $thread), [
            'poll_questions' => [
                ['prompt' => 'Logged?', 'options' => [['label' => 'Yes'], ['label' => 'No']]],
            ],
        ]);

        $this->assertDatabaseHas('thread_moderation_logs', [
            'thread_id' => $thread->id,
            'moderator_id' => $moderator->id,
            'action' => 'poll_created',
        ]);
    }

    private function createPublicThread(): Thread
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-poll-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-poll-'.Str::lower(Str::random(8)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Poll Test Thread',
            'slug' => 'poll-test-thread-'.Str::lower(Str::random(8)),
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        return $thread;
    }

    private function createModerator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
