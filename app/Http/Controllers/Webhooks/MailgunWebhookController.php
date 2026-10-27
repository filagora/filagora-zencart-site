<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMailgunWebhook;
use App\Models\WebhookEvent;
use App\Services\Mailgun\MailgunWebhookHandler;
use App\WebhookProvider;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailgunWebhookController extends Controller
{
    public function __construct(
        private readonly MailgunWebhookHandler $handler
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! isset($payload['signature'], $payload['event-data'])) {
            Log::warning('Invalid Mailgun webhook payload structure.', [
                'payload' => $payload,
            ]);

            return response()->json([
                'message' => 'Invalid payload.',
            ], 400);
        }

        if (! $this->isValidSignature($payload['signature'])) {
            Log::warning('Invalid Mailgun webhook signature.', [
                'signature' => $payload['signature'],
            ]);

            // 406 tells Mailgun not to keep retrying this request.
            return response()->json([
                'message' => 'Invalid signature.',
            ], 406);
        }

        $isSyncQueue = config('queue.default') === 'sync';

        // if queue is sync (no queue enabled), process immediately and return result in response
        if ($isSyncQueue) {
            $result = $this->handler->handle($payload['event-data']);

            return response()->json([
                'status' => 'processed',
                'result' => $result,
            ]);
        }

        // queue the job for async processing and return immediately
        ProcessMailgunWebhook::dispatch($payload['event-data']); // 'webhooks' queue is specified in the job class

        return response()->json([
            'status' => 'queued',
            'result' => null,
        ]);
    }

    /**
     * Mailgun signs timestamp + token rather than the request body, so unlike a
     * body-hash scheme this is not sensitive to global middleware rewriting input:
     * the hex token and integer timestamp carry no whitespace for TrimStrings to
     * strip. That is a property of Mailgun's scheme, not a general licence to
     * verify against $request->all() — if Mailgun ever signs the body, this must
     * read $request->getContent() instead. See PayPalWebhookController.
     */
    private function isValidSignature(array $signature): bool
    {
        $token = $signature['token'] ?? null;
        $timestamp = $signature['timestamp'] ?? null;
        $receivedSignature = $signature['signature'] ?? null;

        if (! $token || ! $timestamp || ! $receivedSignature) {
            return false;
        }

        // Freshness check: reject if the timestamp is more than 5 minutes old to prevent replay attacks.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signingKey = config('services.mailgun.webhook_signing_key');

        if (! $signingKey) {
            Log::error('MAILGUN_WEBHOOK_SIGNING_KEY is not configured.');

            return false;
        }

        $calculatedSignature = hash_hmac(
            'sha256',
            $timestamp.$token,
            $signingKey
        );

        if (! hash_equals($calculatedSignature, $receivedSignature)) {
            return false;
        }

        // Record the token after proving the request is genuinely from Mailgun.
        // This prevents replay attacks by ensuring the same token cannot be reused within a short time frame.
        // The record is durable rather than cached, so a deploy's cache flush cannot let a
        // redelivery of an already-processed webhook through as if it were new.
        return WebhookEvent::claim(WebhookProvider::Mailgun, $token, now()->addMinutes(10));
    }
}
