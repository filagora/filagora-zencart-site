<?php

namespace Tests\Unit\Ai;

use App\Ai\DigitalOceanEmbeddingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * describeFailure() turns a failed embeddings request into words that say who
 * is at fault, from what was observed: the HTTP status if the provider
 * answered, or whether a plain request to the same host gets an answer. The
 * incident behind it: a provider whose embeddings service stalled printed only
 * "Operation timed out after 10002 milliseconds with 0 bytes received", and it
 * took four manual tests to learn the gateway, the key and the account were
 * all fine.
 */
class EmbeddingFailureDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.digitalocean.embeddings.api_key' => 'do-secret-key']);
        // A request that no fake matches must fail the test, not reach the real provider.
        Http::preventStrayRequests();
    }

    public function test_a_timeout_while_the_host_still_answers_points_at_the_embeddings_service(): void
    {
        Http::fake([
            'inference.do-ai.run/v1/embeddings' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10002 milliseconds'),
            'inference.do-ai.run/*' => Http::response('not found', 404),
        ]);

        $text = $this->describe();

        $this->assertStringContainsString('inference.do-ai.run is reachable', $text);
        $this->assertStringContainsString('got HTTP 404', $text);
        $this->assertStringContainsString('got no response before it timed out', $text);
        $this->assertStringContainsString("the provider's embeddings service itself, not the network or the key", $text);
    }

    public function test_a_host_that_does_not_answer_at_all_is_reported_as_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $text = $this->describe();

        $this->assertStringContainsString('inference.do-ai.run could not be reached at all (the connection timed out)', $text);
        $this->assertStringNotContainsString('is reachable', $text);
    }

    public function test_a_connection_failure_that_is_not_a_timeout_does_not_claim_one(): void
    {
        Http::fake([
            'inference.do-ai.run/v1/embeddings' => fn () => throw new ConnectionException('cURL error 7: Failed to connect'),
            'inference.do-ai.run/*' => Http::response('not found', 404),
        ]);

        $text = $this->describe();

        $this->assertStringContainsString('failed: cURL error 7', $text);
        $this->assertStringNotContainsString('timed out', $text);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function statuses(): array
    {
        return [
            '401' => [401, 'the key is wrong or has been revoked'],
            '402' => [402, 'check the provider account balance'],
            '403' => [403, 'the key is not allowed to use this model'],
            '404' => [404, 'the endpoint or model was not found'],
            '429' => [429, 'rate limited'],
            '503' => [503, "an error on the provider's side"],
        ];
    }

    #[DataProvider('statuses')]
    public function test_a_rejected_request_names_the_status_and_what_it_usually_means(int $status, string $hint): void
    {
        Http::fake(['inference.do-ai.run/*' => Http::response(['error' => 'no'], $status)]);

        $text = $this->describe();

        $this->assertStringContainsString("answered the embeddings request with HTTP {$status}", $text);
        $this->assertStringContainsString($hint, $text);
    }

    public function test_an_unexpected_failure_is_reported_with_its_own_message(): void
    {
        $this->assertSame(
            'the embeddings request failed: boom',
            (new DigitalOceanEmbeddingProvider)->describeFailure(new RuntimeException('boom')),
        );
    }

    public function test_the_description_never_contains_the_key(): void
    {
        Http::fake([
            'inference.do-ai.run/v1/embeddings' => fn () => throw new ConnectionException('timed out'),
            'inference.do-ai.run/*' => Http::response('not found', 404),
        ]);

        $this->assertStringNotContainsString('do-secret-key', $this->describe());
    }

    /**
     * Makes a real request through the provider under whatever Http::fake
     * the test set up, and describes the failure it throws.
     */
    private function describe(): string
    {
        $provider = new DigitalOceanEmbeddingProvider;

        try {
            $provider->embedBatch(['ping']);
        } catch (\Throwable $failure) {
            return $provider->describeFailure($failure);
        }

        $this->fail('The embeddings request was expected to fail.');
    }
}
