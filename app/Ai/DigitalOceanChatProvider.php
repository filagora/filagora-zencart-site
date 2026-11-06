<?php

namespace App\Ai;

use Filagora\Ai\Providers\AbstractOpenAiCompatibleChatProvider;

/**
 * DigitalOcean Serverless Inference's chat endpoint - assumed OpenAI-
 * compatible the same way its embeddings endpoint is (see
 * DigitalOceanEmbeddingProvider's docblock), but that similarity was only
 * ever confirmed for embeddings specifically. Verify the chat endpoint's
 * actual streaming response shape against DO's own API reference before
 * relying on this in production - this is flagged
 * explicitly as unverified.
 */
class DigitalOceanChatProvider extends AbstractOpenAiCompatibleChatProvider
{
    public function providerName(): string
    {
        return 'digitalocean';
    }

    public function model(): string
    {
        return (string) config('ai.digitalocean.chat.model');
    }

    protected function endpoint(): string
    {
        return 'https://inference.do-ai.run/v1/chat/completions';
    }

    protected function apiKey(): string
    {
        return (string) config('ai.digitalocean.chat.api_key');
    }
}
