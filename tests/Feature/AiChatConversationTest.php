<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Enums\AiChatMessageRole;
use Filagora\Ai\Models\AiChatConversation;
use Filagora\Ai\Services\ChatRetrieval;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeChatProvider;
use Tests\TestCase;

class AiChatConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filagora-ai.chat.enabled' => true]);
        // Retrieval is disabled by default in these tests (ChatRetrieval
        // itself short-circuits on filagora-ai.chat.enabled being false at the
        // point it's called) - the mock below controls its output explicitly
        // instead so tests aren't coupled to real Scout/Meilisearch search.
        $this->mock(ChatRetrieval::class)
            ->shouldReceive('retrieve')
            ->andReturn([]);
    }

    public function test_sending_a_first_message_creates_a_conversation_with_a_user_and_assistant_message(): void
    {
        $this->app->instance(ChatProvider::class, new FakeChatProvider(['Hello', ' there']));
        $user = $this->entitledUser();

        $response = $this->actingAs($user)->post(route('ai-chat.store'), ['message' => 'What is Zen Cart?']);
        $response->assertOk();
        // StreamedResponse only runs its callback (and thus the persistence
        // side effects it triggers) when its content is actually consumed.
        $response->streamedContent();

        $conversation = AiChatConversation::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('What is Zen Cart?', $conversation->title);

        $messages = $conversation->messages()->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame(AiChatMessageRole::USER, $messages[0]->role);
        $this->assertSame('What is Zen Cart?', $messages[0]->content);
        $this->assertSame(AiChatMessageRole::ASSISTANT, $messages[1]->role);
        $this->assertSame('Hello there', $messages[1]->content);
    }

    public function test_retrieved_context_is_stored_on_the_assistant_message_when_present(): void
    {
        $this->mock(ChatRetrieval::class)
            ->shouldReceive('retrieve')
            ->andReturn([[
                'type' => 'post',
                'id' => 1,
                'title' => 'Some Thread',
                'url' => 'https://example.test/threads/1',
                'excerpt' => 'An excerpt.',
            ]]);
        $this->app->instance(ChatProvider::class, new FakeChatProvider(['An answer.']));
        $user = $this->entitledUser();

        $this->actingAs($user)->post(route('ai-chat.store'), ['message' => 'Tell me about it'])->assertOk()->streamedContent();

        $assistantMessage = AiChatConversation::query()->where('user_id', $user->id)->firstOrFail()
            ->messages()->where('role', AiChatMessageRole::ASSISTANT->value)->firstOrFail();

        $this->assertNotEmpty($assistantMessage->retrieved_context);
        $this->assertSame('Some Thread', $assistantMessage->retrieved_context[0]['title']);
    }

    public function test_a_failed_provider_call_does_not_persist_a_partial_assistant_message(): void
    {
        $this->app->instance(ChatProvider::class, new FakeChatProvider(shouldFail: true));
        $user = $this->entitledUser();

        $this->actingAs($user)->post(route('ai-chat.store'), ['message' => 'hi'])->assertOk()->streamedContent();

        $conversation = AiChatConversation::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertSame(
            AiChatMessageRole::USER,
            $conversation->messages()->first()->role,
        );
    }

    public function test_saved_replies_are_marked_for_markdown_rendering_and_what_the_member_typed_is_not(): void
    {
        $user = $this->entitledUser();
        $conversation = AiChatConversation::query()->create(['user_id' => $user->id, 'title' => 't', 'provider' => 'fake', 'model' => 'fake']);
        $conversation->messages()->create(['role' => AiChatMessageRole::USER, 'content' => 'what is **this** <b>x</b>']);
        $conversation->messages()->create(['role' => AiChatMessageRole::ASSISTANT, 'content' => "1. **Back up** first\n2. <script>alert(1)</script>"]);

        $html = $this->actingAs($user)->get(route('ai-chat.show', $conversation))->assertOk()->getContent();

        // Exactly one message (the reply) is handed to the markdown renderer...
        $this->assertSame(1, substr_count($html, 'data-ai-chat-markdown'));
        // ...and the page itself never emits message text as live markup.
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    public function test_conversation_title_is_set_from_the_first_message_and_unchanged_by_later_ones(): void
    {
        $this->app->instance(ChatProvider::class, new FakeChatProvider(['ok']));
        $user = $this->entitledUser();

        $this->actingAs($user)->post(route('ai-chat.store'), ['message' => 'First question'])->assertOk()->streamedContent();
        $conversation = AiChatConversation::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->post(route('ai-chat.messages.store', $conversation), ['message' => 'Second question'])->assertOk()->streamedContent();

        $this->assertSame('First question', $conversation->fresh()->title);
    }

    public function test_a_first_message_longer_than_the_title_column_still_starts_a_conversation(): void
    {
        $this->app->instance(ChatProvider::class, new FakeChatProvider(['ok']));
        $user = $this->entitledUser();

        $this->actingAs($user)->post(route('ai-chat.store'), ['message' => str_repeat('word ', 200)])->assertOk()->streamedContent();

        $conversation = AiChatConversation::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(120, mb_strlen($conversation->title));
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_deleting_a_conversation_removes_its_messages(): void
    {
        $this->app->instance(ChatProvider::class, new FakeChatProvider(['ok']));
        $user = $this->entitledUser();

        $this->actingAs($user)->post(route('ai-chat.store'), ['message' => 'hi'])->assertOk()->streamedContent();
        $conversation = AiChatConversation::query()->where('user_id', $user->id)->firstOrFail();
        $conversationId = $conversation->id;

        $this->actingAs($user)->post(route('ai-chat.destroy', $conversation))->assertRedirect();

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversationId]);
        $this->assertDatabaseMissing('ai_chat_messages', ['conversation_id' => $conversationId]);
    }

    private function entitledUser(): User
    {
        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'ai_chat.access',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        return $user;
    }
}
