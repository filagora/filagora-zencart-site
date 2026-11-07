<?php

namespace Tests\Feature\Ai;

use App\Ai\PluginReviewModel;
use Filagora\Ai\Models\AiChatUsage;
use Filagora\PluginsSecurity\Analysis\Analyzers\LlmAnalyzer;
use Filagora\PluginsSecurity\Contracts\LlmReviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PluginReviewModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.plugin_review.api_key' => 'test-key',
            'ai.plugin_review.model' => 'review-model',
            'ai.plugin_review.endpoint' => 'https://llm.example.test/v1/chat/completions',
            'ai.plugin_review.retries' => 0,
            'ai.plugin_review.retry_delay_ms' => 0,
            'ai.plugin_review.max_tokens' => 1234,
        ]);
        Http::preventStrayRequests();
    }

    public function test_it_is_the_reviewer_the_package_uses(): void
    {
        $this->assertInstanceOf(PluginReviewModel::class, app(LlmReviewer::class));
    }

    public function test_it_is_configured_only_with_a_key_and_a_model(): void
    {
        $this->assertTrue((new PluginReviewModel)->isConfigured());
        $this->assertSame('review-model', (new PluginReviewModel)->model());

        config(['ai.plugin_review.api_key' => null]);
        $this->assertFalse((new PluginReviewModel)->isConfigured());

        config(['ai.plugin_review.api_key' => 'k', 'ai.plugin_review.model' => '']);
        $this->assertFalse((new PluginReviewModel)->isConfigured());
    }

    public function test_it_sends_the_messages_with_the_key_and_the_token_limit_and_answers_the_text(): void
    {
        Http::fake(['llm.example.test/*' => Http::response([
            'choices' => [['message' => ['content' => '[]'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500],
        ])]);

        $answer = (new PluginReviewModel)->complete([['role' => 'user', 'content' => 'Review this.']]);

        $this->assertSame('[]', $answer);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'review-model'
            && $request['max_tokens'] === 1234
            && $request['messages'] === [['role' => 'user', 'content' => 'Review this.']]);
    }

    public function test_every_call_goes_in_the_usage_log_as_a_system_call_under_its_own_feature(): void
    {
        Http::fake(['llm.example.test/*' => Http::response([
            'choices' => [['message' => ['content' => '[]']]],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500],
        ])]);

        (new PluginReviewModel)->complete([['role' => 'user', 'content' => 'x']]);

        $usage = AiChatUsage::query()->sole();
        $this->assertSame('plugin_security_scan', $usage->context);
        $this->assertSame('digitalocean', $usage->provider);
        $this->assertSame('review-model', $usage->model);
        $this->assertNull($usage->user_id);
        $this->assertSame(1000, $usage->input_tokens);
        $this->assertSame(500, $usage->output_tokens);
        $this->assertTrue($usage->succeeded);
    }

    public function test_a_failed_request_is_recorded_and_thrown(): void
    {
        Http::fake(['llm.example.test/*' => Http::response('nope', 500)]);

        try {
            (new PluginReviewModel)->complete([['role' => 'user', 'content' => 'x']]);
            $this->fail('The failure should have been thrown.');
        } catch (RequestException) {
            // expected
        }

        $usage = AiChatUsage::query()->sole();
        $this->assertFalse($usage->succeeded);
        $this->assertSame('plugin_security_scan', $usage->context);
    }

    public function test_an_empty_answer_says_why_and_how_to_fix_a_cut_off_one(): void
    {
        Http::fake(['llm.example.test/*' => Http::response([
            'choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 24000],
        ])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('finish reason: length');

        (new PluginReviewModel)->complete([['role' => 'user', 'content' => 'x']]);
    }

    public function test_the_settings_reach_the_package_under_the_old_environment_names(): void
    {
        $this->assertSame(150, config('filagora-plugins-security.analyzers.llm.max_files'));
        $this->assertSame(120000, config('filagora-plugins-security.analyzers.llm.max_batch_bytes'));
        $this->assertSame(6, config('filagora-plugins-security.analyzers.llm.max_batches'));
        $this->assertSame(600, config('filagora-plugins-security.analyzers.llm.timeout_seconds'));
        $this->assertSame('Zen Cart plugins', config('filagora-plugins-security.analyzers.llm.subject'));
    }

    public function test_the_review_is_off_unless_switched_on_and_a_model_is_configured(): void
    {
        $this->assertFalse(app(LlmAnalyzer::class)->isAvailable());

        config(['filagora-plugins-security.analyzers.llm.enabled' => true]);
        $this->assertTrue(app(LlmAnalyzer::class)->isAvailable());

        config(['ai.plugin_review.model' => null]);
        $this->assertFalse(app(LlmAnalyzer::class)->isAvailable());
    }
}
