<?php

namespace App\Ai;

use Filagora\Ai\Services\ChatUsageRecorder;
use Filagora\PluginsSecurity\Contracts\LlmReviewer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The language model behind the staff "AI review" of a plugin release: a hosted model that speaks the
 * chat-completions format, called with the settings in `ai.plugin_review`. Every call is recorded in
 * the AI usage log, under the feature `plugin_security_scan` and with no person (it is a system call).
 */
class PluginReviewModel implements LlmReviewer
{
    public const PROVIDER = 'digitalocean';

    public const USAGE_CONTEXT = 'plugin_security_scan';

    public function isConfigured(): bool
    {
        return filled(config('ai.plugin_review.api_key')) && filled(config('ai.plugin_review.model'));
    }

    public function model(): string
    {
        return (string) config('ai.plugin_review.model');
    }

    public function complete(array $messages): string
    {
        $config = (array) config('ai.plugin_review');
        $model = $this->model();
        $recorder = app(ChatUsageRecorder::class);

        try {
            $response = Http::withToken((string) ($config['api_key'] ?? ''))
                ->timeout((int) ($config['timeout_seconds'] ?? 600))
                ->connectTimeout(10)
                ->retry(
                    1 + max(0, (int) ($config['retries'] ?? 1)),
                    max(0, (int) ($config['retry_delay_ms'] ?? 2000)),
                    fn (Throwable $e): bool => $e instanceof ConnectionException,
                    throw: false,
                )
                ->acceptJson()
                ->post((string) $config['endpoint'], [
                    'model' => $model,
                    'max_tokens' => (int) ($config['max_tokens'] ?? 8000),
                    'messages' => $messages,
                ]);

            if ($response->failed()) {
                $response->throw();
            }
        } catch (Throwable $e) {
            $recorder->recordFailure(self::PROVIDER, $model, null, self::USAGE_CONTEXT);

            throw $e;
        }

        $usage = (array) $response->json('usage', []);
        $recorder->recordSuccess(
            self::PROVIDER,
            $model,
            is_numeric($usage['prompt_tokens'] ?? null) ? (int) $usage['prompt_tokens'] : null,
            is_numeric($usage['completion_tokens'] ?? null) ? (int) $usage['completion_tokens'] : null,
            null,
            self::USAGE_CONTEXT,
        );

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            $reason = (string) $response->json('choices.0.finish_reason', 'unknown');

            throw new RuntimeException('The model returned no answer (finish reason: '.$reason.')'
                .($reason === 'length' ? '; it ran out of tokens while reasoning - raise PLUGIN_SECURITY_SCAN_LLM_MAX_TOKENS.' : '.'));
        }

        return $content;
    }
}
