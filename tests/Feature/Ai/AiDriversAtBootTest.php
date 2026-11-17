<?php

namespace Tests\Feature\Ai;

use App\Ai\DigitalOceanChatProvider;
use App\Ai\DigitalOceanEmbeddingProvider;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Services\ChatProviderManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * The package providers boot before the site's, and some of them (the thread vector index among them)
 * ask for the embedding provider while they boot. The site's drivers therefore have to be known by
 * then: registered when the manager is resolved, not in the site provider's own boot().
 *
 * The switches are set after the config files load and before any provider registers, which is where
 * a real environment puts them. Setting them in the process environment instead would depend on what
 * a `.env` file and earlier tests left behind.
 */
class AiDriversAtBootTest extends TestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set([
                'filagora-ai.embeddings.enabled' => true,
                'filagora-ai.embeddings.driver' => 'digitalocean',
                'filagora-ai.chat.driver' => 'digitalocean',
            ]);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_the_application_boots_with_embeddings_on_and_the_sites_driver_chosen(): void
    {
        $this->assertTrue((bool) config('filagora-ai.embeddings.enabled'));
        $this->assertInstanceOf(DigitalOceanEmbeddingProvider::class, app(EmbeddingProvider::class));
        $this->assertInstanceOf(DigitalOceanChatProvider::class, app(ChatProviderManager::class)->resolve());
    }
}
