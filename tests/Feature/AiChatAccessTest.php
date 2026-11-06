<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Ai\Models\AiChatConversation;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filagora-ai.chat.enabled' => true]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('ai-chat.index'));
        $response->assertRedirect();
        $this->assertStringContainsString('auth=login', $response->headers->get('Location'));
    }

    public function test_authenticated_user_without_entitlement_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('ai-chat.index'))->assertForbidden();
        $this->actingAs($user)->post(route('ai-chat.store'), ['message' => 'hi'])->assertForbidden();
    }

    public function test_entitled_user_can_view_the_index(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'ai_chat.access');

        $this->actingAs($user)->get(route('ai-chat.index'))->assertOk();
    }

    public function test_feature_flag_off_forbids_even_an_entitled_user(): void
    {
        config(['filagora-ai.chat.enabled' => false]);

        $user = User::factory()->create();
        $this->grant($user, 'ai_chat.access');

        $this->actingAs($user)->get(route('ai-chat.index'))->assertForbidden();
    }

    public function test_a_user_cannot_view_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $this->grant($owner, 'ai_chat.access');
        $conversation = AiChatConversation::query()->create(['user_id' => $owner->id, 'title' => 'Mine']);

        $other = User::factory()->create();
        $this->grant($other, 'ai_chat.access');

        $this->actingAs($other)->get(route('ai-chat.show', $conversation))->assertForbidden();
    }

    public function test_a_user_cannot_delete_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $this->grant($owner, 'ai_chat.access');
        $conversation = AiChatConversation::query()->create(['user_id' => $owner->id, 'title' => 'Mine']);

        $other = User::factory()->create();
        $this->grant($other, 'ai_chat.access');

        $this->actingAs($other)->post(route('ai-chat.destroy', $conversation))->assertForbidden();
        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_the_owner_can_delete_their_own_conversation(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'ai_chat.access');
        $conversation = AiChatConversation::query()->create(['user_id' => $user->id, 'title' => 'Mine']);

        $this->actingAs($user)->post(route('ai-chat.destroy', $conversation))->assertRedirect(route('ai-chat.index'));
        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_revoking_the_entitlement_takes_effect_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $grant = $this->grant($user, 'ai_chat.access');

        $this->actingAs($user)->get(route('ai-chat.index'))->assertOk();

        $grant->delete();

        $this->actingAs($user)->get(route('ai-chat.index'))->assertForbidden();
    }

    private function grant(User $user, string $entitlement): UserEntitlementGrant
    {
        return UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => $entitlement,
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
    }
}
