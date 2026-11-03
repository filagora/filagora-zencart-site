<?php

namespace Tests\Feature;

use App\Http\Middleware\AddMonitoringContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class MonitoringContextMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_global_middleware_adds_a_request_id_to_the_health_response(): void
    {
        $response = $this->get('/up');

        $response->assertOk()->assertHeader('X-Request-ID');
    }

    public function test_it_generates_a_request_id_and_shares_it_with_logs(): void
    {
        $response = $this->runMiddleware();

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertSame($requestId, $this->responseJson($response)['request_id']);
        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', $requestId);
    }

    public function test_it_preserves_a_valid_inbound_request_id(): void
    {
        $response = $this->runMiddleware('edge-request_123.abc');

        $this->assertSame('edge-request_123.abc', $response->headers->get('X-Request-ID'));
        $this->assertSame('edge-request_123.abc', $this->responseJson($response)['request_id']);
    }

    public function test_it_replaces_invalid_or_oversized_request_ids(): void
    {
        foreach (['contains spaces', '<script>', str_repeat('a', 129)] as $invalidRequestId) {
            $response = $this->runMiddleware($invalidRequestId);

            $this->assertNotSame($invalidRequestId, $response->headers->get('X-Request-ID'));
        }
    }

    public function test_request_context_does_not_leak_between_requests(): void
    {
        $firstResponse = $this->runMiddleware('first-request');
        $secondResponse = $this->runMiddleware();

        $this->assertSame('first-request', $this->responseJson($firstResponse)['request_id']);
        $this->assertNotSame('first-request', $this->responseJson($secondResponse)['request_id']);
    }

    private function runMiddleware(?string $requestId = null): Response
    {
        $server = $requestId === null ? [] : ['HTTP_X_REQUEST_ID' => $requestId];
        $request = Request::create('/test', server: $server);

        return app(AddMonitoringContext::class)->handle(
            $request,
            static fn (): Response => response()->json(Log::sharedContext()),
        );
    }

    /** @return array<string, string> */
    private function responseJson(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
