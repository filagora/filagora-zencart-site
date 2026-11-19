<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFaiveloWebhookEvent;
use App\Models\WebhookEvent;
use App\Services\Faivelo\FaiveloWebhookHandler;
use App\WebhookProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class FaiveloWebhookController extends Controller
{
    /** Faivelo's SDK rejects timestamps older than this; so do we. */
    private const TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly FaiveloWebhookHandler $handler
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = $request->header('X-Faivelo-Signature');

        if (! is_string($signature) || $signature === '') {
            Log::channel('faivelo')->warning('Missing Faivelo webhook signature.');

            return response()->json(['message' => 'Missing signature.'], 400);
        }

        // The HMAC covers the raw bytes Faivelo sent, so hash getContent(), never $request->all().
        // See PayPalWebhookController for what happens when that rule is broken.
        if (! $this->isValidSignature($request->getContent(), $signature)) {
            Log::channel('faivelo')->warning('Invalid Faivelo webhook signature.');

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $eventData = $request->all();

        if (! is_string($eventData['type'] ?? null) || ! is_array($eventData['data'] ?? null)) {
            Log::channel('faivelo')->warning('Invalid Faivelo webhook payload structure.');

            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        // Faivelo delivers at least once, so a duplicate is acknowledged with a 2xx and skipped.
        if (! $this->isNewEvent($eventData)) {
            return response()->json(['status' => 'duplicate', 'result' => null]);
        }

        try {
            if (config('queue.default') === 'sync') {
                return response()->json([
                    'status' => 'processed',
                    'result' => $this->handler->handle($eventData),
                ]);
            }

            ProcessFaiveloWebhookEvent::dispatch($eventData);
        } catch (Throwable $e) {
            // Faivelo retries on a non-2xx, so the claim must not make that retry look like a duplicate.
            WebhookEvent::query()
                ->where('provider', WebhookProvider::Faivelo)
                ->where('event_key', $eventData['id'] ?? '')
                ->delete();

            throw $e;
        }

        return response()->json(['status' => 'queued', 'result' => null]);
    }

    /**
     * The header is `t=<timestamp>,v1=<signature>`; the signature is HMAC-SHA256 of `<timestamp>.<raw body>`.
     */
    private function isValidSignature(string $rawBody, string $header): bool
    {
        $signingKey = config('services.faivelo.webhook_signing_key');

        if (! $signingKey) {
            Log::channel('faivelo')->error('FAIVELO_WEBHOOK_SIGNING_KEY is not configured.');

            return false;
        }

        $timestamp = '';
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($name === 't') {
                $timestamp = $value;
            } elseif ($name === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if (! ctype_digit($timestamp) || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, (string) $signingKey);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Held in the durable replay table rather than the cache, so a deploy's cache flush cannot let a
     * redelivered event through as new. See WebhookEvent.
     *
     * @param  array<string, mixed>  $eventData
     */
    private function isNewEvent(array $eventData): bool
    {
        $eventId = $eventData['id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            return true;
        }

        return WebhookEvent::claim(WebhookProvider::Faivelo, $eventId, now()->addHours(24));
    }
}
