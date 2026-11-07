<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\AiForums\Assist\ThreadAssistGate;
use Filagora\AiForums\Assist\ThreadAssistService;
use Filagora\Forums\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Streams a suggested answer to a thread for the staff member who asked, in the chat's frame format.
 * Nothing is stored against the thread.
 */
class ThreadAssistController extends Controller
{
    public function __construct(
        private readonly ThreadAssistGate $gate,
        private readonly ThreadAssistService $assist,
    ) {}

    public function stream(Request $request, Thread $thread): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->gate->allows($user, $thread), 403);

        // Before the response starts: a failure here is an ordinary error page, and the
        // stored-answer case spends nothing.
        $plan = $this->assist->plan($thread, $user);

        return response()->stream(function () use ($plan, $user, $thread): void {
            $this->send(['sources' => $plan->sources, 'version_note' => implode(' ', $plan->notes), 'stored' => $plan->isStored()]);

            try {
                foreach ($this->assist->stream($plan, $user) as $delta) {
                    // An empty piece means the model is still working: say so, so nginx and
                    // Cloudflare do not drop a quiet connection.
                    $this->send($delta === '' ? ['ping' => true] : ['delta' => $delta]);
                }
            } catch (Throwable $e) {
                Log::warning('AI thread assist stream failed.', [
                    'thread_id' => $thread->id,
                    'user_id' => $user->id,
                    'exception' => $e->getMessage(),
                ]);
                $this->send(['error' => true]);
            }

            $this->send(['done' => true]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): void
    {
        echo 'data: '.json_encode($payload)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
