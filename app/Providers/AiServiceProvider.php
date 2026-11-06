<?php

namespace App\Providers;

use App\Ai\DigitalOceanChatProvider;
use App\Ai\DigitalOceanEmbeddingProvider;
use Filagora\Ai\Services\ChatProviderManager;
use Filagora\Ai\Services\EmbeddingProviderManager;
use Illuminate\Support\ServiceProvider;

/**
 * What the site adds to `filagora-ai`: the DigitalOcean providers, their prices, and how the
 * assistant introduces itself. The package's config merges one level deep, so these are set key by
 * key rather than by repeating its arrays.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'filagora-ai.chat.system_prompt' => config('ai.system_prompt'),
            'filagora-ai.embeddings.cost_per_million_tokens.digitalocean' => config('ai.digitalocean.embeddings.cost_per_million_tokens'),
        ]);
    }

    public function boot(): void
    {
        $this->app->make(ChatProviderManager::class)->extend('digitalocean', fn () => new DigitalOceanChatProvider);
        $this->app->make(EmbeddingProviderManager::class)->extend('digitalocean', fn () => new DigitalOceanEmbeddingProvider);
    }
}
