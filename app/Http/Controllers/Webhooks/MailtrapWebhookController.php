<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMailtrapWebhookEvent;
use App\Models\WebhookEvent;
use App\Services\Mailtrap\MailtrapWebhookHandler;
use App\WebhookProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailtrapWebhookController extends Controller
{
    public function __construct(
        private readonly MailtrapWebhookHandler $handler
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = $request->header('Mailtrap-Signature');

        if (! is_string($signature) || $signature === '') {
            Log::warning('Missing Mailtrap webhook signature.');

            return response()->json([
                'message' => 'Missing signature.',
            ], 400);
        }

        // Hashed over the raw body, never $request->all(): the HMAC covers the bytes
        // Mailtrap sent, and global middleware rewrites input before it is returned.
        // See PayPalWebhookController for what happens when that rule is broken.
        if (! $this->isValidSignature($request->getContent(), $signature)) {
            Log::warning('Invalid Mailtrap webhook signature.');

            // 406 tells Mailtrap not to keep retrying this request.
            return response()->json([
                'message' => 'Invalid signature.',
            ], 406);
        }

        $payload = $request->all();
        $events = $payload['events'] ?? null;

        if (! is_array($events)) {
            Log::warning('Invalid Mailtrap webhook payload structure.', [
                'payload' => $payload,
            ]);

            return response()->json([
                'message' => 'Invalid payload.',
            ], 400);
        }

        $isSyncQueue = config('queue.default') === 'sync';
        $results = [];

        foreach ($events as $eventData) {
            if (! is_array($eventData) || ! $this->isNewEvent($eventData)) {
                continue;
            }

            // if queue is sync (no queue enabled), process immediately and collect the result
            if ($isSyncQueue) {
                $results[] = $this->handler->handle($eventData);

                continue;
            }

            // queue a job per event for async processing ('webhooks' queue is specified in the job class)
            ProcessMailtrapWebhookEvent::dispatch($eventData);
        }

        if ($isSyncQueue) {
            return response()->json([
                'status' => 'processed',
                'result' => $results,
            ]);
        }

        return response()->json([
            'status' => 'queued',
            'result' => null,
        ]);
    }

    private function isValidSignature(string $rawBody, string $signature): bool
    {
        $signingKey = config('services.mailtrap.webhook_signing_key');

        if (! $signingKey) {
            Log::error('MAILTRAP_WEBHOOK_SIGNING_KEY is not configured.');

            return false;
        }

        $calculatedSignature = hash_hmac('sha256', $rawBody, $signingKey);

        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Mailtrap recommends deduplicating by event_id rather than a replay time-window,
     * since webhook deliveries can legitimately retry over an extended period.
     *
     * The seen-event record is durable rather than cached, so a deploy's cache flush
     * cannot let a redelivery of an already-processed event through as if it were new.
     */
    private function isNewEvent(array $eventData): bool
    {
        $eventId = $eventData['event_id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            return true;
        }

        return WebhookEvent::claim(WebhookProvider::Mailtrap, $eventId, now()->addHours(24));
    }
}
