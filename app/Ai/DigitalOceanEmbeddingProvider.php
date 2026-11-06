<?php

namespace App\Ai;

use Filagora\Ai\Providers\AbstractHttpEmbeddingProvider;

/**
 * DigitalOcean's Serverless Inference embeddings endpoint - request/response
 * shape confirmed identical to OpenAI's (data[].embedding, usage.total_tokens)
 * at https://docs.digitalocean.com/reference/api/reference/embeddings/, so it
 * fits the shared AbstractHttpEmbeddingProvider unchanged. Primarily useful
 * for centralizing billing onto DigitalOcean (production already runs there)
 * rather than for cost or quality - see ai.digitalocean.embeddings.embedding_model
 * for the model in use (default BAAI/bge-m3, 8192-token window).
 */
class DigitalOceanEmbeddingProvider extends AbstractHttpEmbeddingProvider
{
    protected function providerName(): string
    {
        return 'digitalocean';
    }

    protected function endpoint(): string
    {
        return 'https://inference.do-ai.run/v1/embeddings';
    }

    protected function apiKey(): string
    {
        return (string) config('ai.digitalocean.embeddings.api_key');
    }

    protected function model(): string
    {
        return (string) config('ai.digitalocean.embeddings.embedding_model');
    }

    public function dimensions(): int
    {
        return (int) config('ai.digitalocean.embeddings.dimensions');
    }
}
