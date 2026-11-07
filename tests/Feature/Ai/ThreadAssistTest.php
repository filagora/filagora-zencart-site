<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\FakeChatProvider;
use Tests\TestCase;

class ThreadAssistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filagora-ai.chat.enabled' => true]);
        Cache::flush();
        $this->app->instance(ChatProvider::class, new FakeChatProvider(['A suggested ', 'answer.']));
        $this->app->instance(EmbeddingProvider::class, new class implements EmbeddingProvider
        {
            public function embed(string $text, ?string $context = null): array
            {
                return [0.1, 0.2];
            }

            public function embedBatch(array $texts, ?string $context = null): array
            {
                return array_map(fn (): array => [0.1, 0.2], $texts);
            }

            public function dimensions(): int
            {
                return 2;
            }
        });
    }

    private function thread(ForumVisibility $visibility = ForumVisibility::PUBLIC): Thread
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'g-'.Str::lower(Str::random(8)), 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Support',
            'slug' => 'support-'.Str::lower(Str::random(8)),
            'visibility' => $visibility,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create(['forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'Blank page', 'slug' => 'blank-'.Str::lower(Str::random(8))]);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'My checkout is blank.']);

        return $thread;
    }

    private function assister(): User
    {
        $user = User::factory()->create();
        $this->grantEntitlement($user, 'operator.ai.thread_assist');

        return $user;
    }

    public function test_someone_with_the_entitlement_sees_the_button_and_the_panel_on_a_thread(): void
    {
        $thread = $this->thread();

        $this->actingAs($this->assister())->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('data-thread-assist-open', false)
            ->assertSee(route('threads.ai-assist', $thread), false);
    }

    public function test_everyone_else_sees_neither(): void
    {
        $thread = $this->thread();

        $this->get(route('threads.show', $thread))->assertOk()->assertDontSee('data-thread-assist', false);
        $this->actingAs(User::factory()->create())->get(route('threads.show', $thread))->assertOk()->assertDontSee('data-thread-assist', false);
    }

    public function test_it_is_off_while_the_chat_is_switched_off(): void
    {
        config(['filagora-ai.chat.enabled' => false]);
        $thread = $this->thread();
        $user = $this->assister();

        $this->actingAs($user)->get(route('threads.show', $thread))->assertDontSee('data-thread-assist', false);
        $this->actingAs($user)->post(route('threads.ai-assist', $thread))->assertForbidden();
    }

    public function test_it_streams_a_suggested_answer_with_its_sources_framed_as_events(): void
    {
        $thread = $this->thread();
        $user = $this->assister();

        $response = $this->actingAs($user)->post(route('threads.ai-assist', $thread));
        $body = $response->streamedContent();

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('data: {"sources":[', $body);
        $this->assertStringContainsString('"stored":false', $body);
        $this->assertStringContainsString('data: {"delta":"A suggested "}', $body);
        $this->assertStringContainsString('data: {"delta":"answer."}', $body);
        $this->assertStringEndsWith('data: {"done":true}'."\n\n", $body);
    }

    public function test_a_second_ask_is_replayed_from_the_stored_answer(): void
    {
        $thread = $this->thread();
        $user = $this->assister();
        $this->actingAs($user)->post(route('threads.ai-assist', $thread))->streamedContent();

        $body = $this->actingAs($user)->post(route('threads.ai-assist', $thread))->streamedContent();

        $this->assertStringContainsString('"stored":true', $body);
        $this->assertStringContainsString('A suggested answer.', str_replace('"}'."\n\n".'data: {"delta":"', '', $body));
    }

    public function test_guests_are_sent_to_log_in_and_people_without_the_entitlement_are_refused(): void
    {
        $thread = $this->thread();

        $this->post(route('threads.ai-assist', $thread))->assertRedirect();
        $this->actingAs(User::factory()->create())->post(route('threads.ai-assist', $thread))->assertForbidden();
    }

    public function test_a_thread_from_a_forum_the_person_could_not_open_is_refused_even_with_the_entitlement(): void
    {
        $thread = $this->thread(ForumVisibility::STAFF_ONLY);

        $this->actingAs($this->assister())->post(route('threads.ai-assist', $thread))->assertForbidden();
    }

    public function test_it_is_throttled_per_person(): void
    {
        $thread = $this->thread();
        $user = $this->assister();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)->post(route('threads.ai-assist', $thread))->streamedContent();
        }

        $this->actingAs($user)->post(route('threads.ai-assist', $thread))->assertStatus(429);
    }

    public function test_the_model_is_introduced_to_the_support_forum(): void
    {
        $this->assertStringContainsString('Zen Cart support forum', (string) config('filagora-ai-forums.assist.system_prompt'));
    }

    public function test_the_ability_maps_to_the_staff_entitlement_lara_forum_used(): void
    {
        $this->assertSame('operator.ai.thread_assist', config('filagora-ai-forums.gates')['filagora.ai.thread-assist']);
    }
}
