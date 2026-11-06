<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\Ai\Http\ChatReplyStream;
use Filagora\Ai\Models\AiChatConversation;
use Filagora\Ai\Services\AiChatService;
use Filagora\Ai\Support\Abilities;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    private const MESSAGE_MAX_LENGTH = 4000;

    public function __construct(
        private readonly AiChatService $aiChatService,
        private readonly ChatReplyStream $replyStream,
    ) {}

    public function index(): View
    {
        $user = $this->requireAiChatAccess();

        return view('ai-chat.index', [
            'conversations' => $this->conversationsOf($user->id),
            'conversation' => null,
            'messages' => collect(),
        ]);
    }

    public function show(AiChatConversation $conversation): View
    {
        $user = $this->requireAiChatAccess();
        abort_unless($user->can('view', $conversation), 403);

        return view('ai-chat.index', [
            'conversations' => $this->conversationsOf($conversation->user_id),
            'conversation' => $conversation,
            'messages' => $conversation->messages()->get(),
        ]);
    }

    public function store(Request $request): StreamedResponse
    {
        $user = $this->requireAiChatAccess();
        abort_unless($user->can('create', AiChatConversation::class), 403);

        $message = $this->validatedMessage($request);

        $conversation = $this->aiChatService->startConversation($user, $message);
        $this->aiChatService->recordUserMessage($conversation, $message);

        return $this->streamReply($conversation, $user, $message);
    }

    public function sendMessage(Request $request, AiChatConversation $conversation): StreamedResponse
    {
        $user = $this->requireAiChatAccess();
        abort_unless($user->can('update', $conversation), 403);

        $message = $this->validatedMessage($request);

        $this->aiChatService->recordUserMessage($conversation, $message);

        return $this->streamReply($conversation, $user, $message);
    }

    public function destroy(AiChatConversation $conversation): RedirectResponse
    {
        $user = $this->requireAiChatAccess();
        abort_unless($user->can('delete', $conversation), 403);

        $conversation->delete();

        return redirect()->route('ai-chat.index');
    }

    private function streamReply(AiChatConversation $conversation, User $user, string $message): StreamedResponse
    {
        return $this->replyStream->response(
            $this->aiChatService->streamAssistantReply($conversation, $user, $message),
            ['conversation_id' => $conversation->id],
            ['conversation_id' => $conversation->id],
        );
    }

    /**
     * @return EloquentCollection<int, AiChatConversation>
     */
    private function conversationsOf(int $userId): EloquentCollection
    {
        return AiChatConversation::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get();
    }

    private function validatedMessage(Request $request): string
    {
        return (string) $request->validate([
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
        ])['message'];
    }

    /**
     * Two independent gates: the operational switch and the entitlement. Either one alone turns
     * the chat off, so an outage or a cost spike needs no revoking of anyone's entitlement.
     */
    private function requireAiChatAccess(): User
    {
        abort_unless((bool) config('filagora-ai.chat.enabled'), 403);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        abort_unless(Gate::forUser($user)->allows(Abilities::CHAT), 403);

        return $user;
    }
}
