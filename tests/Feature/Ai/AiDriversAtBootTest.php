<?php

namespace Tests\Feature\Ai;

use App\Ai\DigitalOceanChatProvider;
use App\Ai\DigitalOceanEmbeddingProvider;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Services\ChatProviderManager;
use Tests\TestCase;

/**
 * The package providers boot before the site's, and some of them (the thread vector index among them)
 * ask for the embedding provider while they boot. The site's drivers therefore have to be known by
 * then: registered when the manager is resolved, not in the site provider's own boot().
 */
class AiDriversAtBootTest extends TestCase
{
    private const SWITCHES = [
        'AI_EMBEDDINGS_ENABLED' => 'true',
        'AI_EMBEDDINGS_DRIVER' => 'digitalocean',
        'AI_CHAT_DRIVER' => 'digitalocean',
    ];

    protected function tearDown(): void
    {
        foreach (array_keys(self::SWITCHES) as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        parent::tearDown();
    }

    public function test_the_application_boots_with_embeddings_on_and_the_sites_driver_chosen(): void
    {
        foreach (self::SWITCHES as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $this->refreshApplication();

        $this->assertTrue((bool) config('filagora-ai.embeddings.enabled'));
        $this->assertInstanceOf(DigitalOceanEmbeddingProvider::class, app(EmbeddingProvider::class));
        $this->assertInstanceOf(DigitalOceanChatProvider::class, app(ChatProviderManager::class)->resolve());
    }
}
