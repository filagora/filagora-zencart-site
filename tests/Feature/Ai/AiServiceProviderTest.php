<?php

namespace Tests\Feature\Ai;

use App\Ai\DigitalOceanChatProvider;
use App\Ai\DigitalOceanEmbeddingProvider;
use Filagora\Ai\Services\ChatProviderManager;
use Filagora\Ai\Services\EmbeddingProviderManager;
use Tests\TestCase;

class AiServiceProviderTest extends TestCase
{
    public function test_the_digitalocean_chat_driver_is_the_sites_own_provider(): void
    {
        config(['filagora-ai.chat.driver' => 'digitalocean']);

        $this->assertInstanceOf(DigitalOceanChatProvider::class, app(ChatProviderManager::class)->resolve());
    }

    public function test_the_digitalocean_embeddings_driver_is_the_sites_own_provider(): void
    {
        config(['filagora-ai.embeddings.driver' => 'digitalocean']);

        $this->assertInstanceOf(DigitalOceanEmbeddingProvider::class, app(EmbeddingProviderManager::class)->resolve());
    }

    public function test_its_embedding_price_reaches_the_package_cost_lookup(): void
    {
        $this->assertEquals(0.02, config('filagora-ai.embeddings.cost_per_million_tokens.digitalocean.bge-m3'));
    }

    public function test_the_assistant_is_introduced_as_the_support_forums_helper(): void
    {
        $this->assertStringContainsString('Zen Cart support forum', (string) config('filagora-ai.chat.system_prompt'));
    }

    public function test_the_package_defaults_survive_the_sites_additions(): void
    {
        $this->assertSame(8, (int) config('filagora-ai.chat.retrieval_top_k'));
        $this->assertSame('ai_chat.access', config('filagora-ai.gates')['filagora.ai.chat']);
    }
}
