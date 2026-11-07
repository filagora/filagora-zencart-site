<?php

namespace App\Providers;

use App\Ai\DigitalOceanChatProvider;
use App\Ai\DigitalOceanEmbeddingProvider;
use App\Ai\PluginReviewModel;
use Filagora\Ai\Services\ChatProviderManager;
use Filagora\Ai\Services\EmbeddingProviderManager;
use Filagora\PluginsSecurity\Contracts\LlmReviewer;
use Illuminate\Support\ServiceProvider;

/**
 * What the site adds to `filagora-ai`: the DigitalOcean providers, their prices, how the assistant
 * introduces itself, and the model behind the plugin security AI review. The package's config merges one level deep, so these are set key by
 * key rather than by repeating its arrays.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmReviewer::class, PluginReviewModel::class);

        config([
            'filagora-ai.chat.system_prompt' => config('ai.system_prompt'),
            'filagora-ai-forums.assist.system_prompt' => config('ai.thread_assist_prompt'),
            'filagora-ai.embeddings.cost_per_million_tokens.digitalocean' => config('ai.digitalocean.embeddings.cost_per_million_tokens'),
        ]);

        foreach (['enabled', 'subject', 'max_files', 'max_file_bytes', 'max_batch_bytes', 'max_batches', 'timeout_seconds'] as $setting) {
            config(["filagora-plugins-security.analyzers.llm.{$setting}" => config("ai.plugin_review.{$setting}")]);
        }
    }

    public function boot(): void
    {
        $this->app->make(ChatProviderManager::class)->extend('digitalocean', fn () => new DigitalOceanChatProvider);
        $this->app->make(EmbeddingProviderManager::class)->extend('digitalocean', fn () => new DigitalOceanEmbeddingProvider);
    }
}
