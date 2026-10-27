<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\Messages\Exceptions\MessageRefused;
use Filagora\Messages\Models\PrivateConversation;
use Filagora\Messages\Models\PrivateConversationParticipant;
use Filagora\Messages\Models\PrivateMessageFolder;
use Filagora\Messages\Services\IgnoredUsers;
use Filagora\Messages\Services\Mailbox;
use Filagora\Messages\Services\Messages;
use Filagora\Messages\Support\Models;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A member's private messages: the folders and the list in each, writing a message, reading and
 * answering a conversation, and moving, trashing and marking conversations. Who may write to whom,
 * what a conversation holds and where it sits are the messages package's; this reads the request
 * and renders.
 */
class MessageController extends Controller
{
    private const CONVERSATIONS_PER_PAGE = 20;

    private const BULK_ACTIONS = ['move', 'trash', 'restore', 'delete', 'mark_read', 'mark_unread'];

    public function __construct(
        private readonly Messages $messages,
        private readonly Mailbox $mailbox,
        private readonly IgnoredUsers $ignored,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $folders = $this->mailbox->foldersFor($user);
        $slug = (string) $request->query('folder', 'inbox');
        $folder = $folders->first(fn (PrivateMessageFolder $candidate): bool => $candidate->slug === $slug)
            ?? $folders->first(fn (PrivateMessageFolder $candidate): bool => $candidate->slug === 'inbox');

        abort_unless($folder instanceof PrivateMessageFolder, 404);

        $isTrash = $folder->slug === 'trash';
        $participantModel = Models::class('participant');
        $messageModel = Models::class('message');

        $participants = $participantModel::query()
            ->with([
                'conversation' => fn ($query) => $query
                    ->withCount('messages')
                    ->with([
                        'messages' => fn ($messages) => $messages->latest()->limit(1)->with('sender:id,username,name,email'),
                        'participants.user:id,username,name,email',
                    ]),
            ])
            ->where('user_id', $user->id)
            ->where('folder_id', $folder->id)
            ->whereNull('deleted_at')
            ->when($isTrash, fn ($query) => $query->latest('trashed_at'))
            ->when(! $isTrash, fn ($query) => $query->orderByDesc(
                $messageModel::query()
                    ->selectRaw('MAX(created_at)')
                    ->whereColumn((new $messageModel)->getTable().'.conversation_id', (new $participantModel)->getTable().'.conversation_id')
            )->orderByDesc('id'))
            ->paginate(self::CONVERSATIONS_PER_PAGE)
            ->withQueryString();

        return view('messages.index', [
            'folder' => $folder,
            'folders' => $folders,
            'moveFolders' => $folders->reject(fn (PrivateMessageFolder $candidate): bool => $candidate->slug === 'trash')->values(),
            'participants' => $participants,
            'isTrashView' => $isTrash,
            'trashRetentionDays' => max(1, (int) config('filagora-messages.trash.retention_days', 30)),
        ]);
    }

    public function compose(Request $request): View|RedirectResponse
    {
        return $request->user() instanceof User ? view('messages.compose', []) : $this->toLogin($request);
    }

    /**
     * Recipient suggestions: members who take private messages and are not blocked either way.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['data' => []], 401);
        }

        $text = trim((string) $request->query('q', ''));

        if (mb_strlen($text) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text).'%';
        $blocked = $this->ignored->blockedIdsFor($user);

        $matches = User::query()
            ->where('allow_private_messages', true)
            ->where('id', '!=', $user->id)
            ->when($blocked !== [], fn ($query) => $query->whereNotIn('id', $blocked))
            ->where(fn ($query) => $query->where('username', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('username')
            ->limit(10)
            ->get(['id', 'username', 'name'])
            ->map(fn (User $match): array => [
                'id' => (int) $match->id,
                'username' => (string) $match->username,
                'name' => (string) ($match->name ?? ''),
                'label' => trim(($match->username ?? '').' · '.($match->name ?: $match->username)),
            ])
            ->all();

        return response()->json(['data' => $matches]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sender = $request->user();

        if (! $sender instanceof User) {
            return $this->toLogin($request);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $usernames = collect(explode(',', (string) ($validated['to'] ?? '')))->map(fn (string $name): string => trim($name))->filter()->unique()->values();

        if ($usernames->isEmpty()) {
            return back()->withErrors(['to' => __('private_messages.errors.recipients_required')])->withInput();
        }

        $recipients = User::query()->whereIn('username', $usernames->all())->where('id', '!=', $sender->id)->get();

        if ($recipients->isEmpty()) {
            return back()->withErrors(['to' => __('private_messages.errors.no_valid_recipients')])->withInput();
        }

        $blocked = $this->blockedAmong($sender, $recipients);

        if ($blocked->isNotEmpty()) {
            return back()->withErrors(['to' => __('private_messages.errors.blocked_recipients', ['users' => $blocked->implode(', ')])])->withInput();
        }

        try {
            $conversation = $this->messages->send($sender, $recipients, (string) $validated['subject'], (string) $validated['body']);
        } catch (MessageRefused $refused) {
            return back()->withErrors([$refused->reason->value === 'empty_subject' ? 'subject' : 'to' => $refused->getMessage()])->withInput();
        }

        return redirect()->route('messages.show', $conversation)->with('message_status', __('private_messages.flash.message_sent'));
    }

    public function show(Request $request, PrivateConversation $conversation): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $folders = $this->mailbox->foldersFor($user);

        try {
            $participant = $this->messages->open($user, $conversation);
        } catch (MessageRefused) {
            abort(403);
        }

        $conversation->load([
            'messages.sender:id,username,name,email',
            'participants.user:id,username,name,email',
            'participants.folder:id,user_id,name,slug,is_system',
        ]);

        return view('messages.show', [
            'conversation' => $conversation,
            'participant' => $participant,
            'moveFolders' => $folders->reject(fn (PrivateMessageFolder $folder): bool => $folder->slug === 'trash')->values(),
            'trashFolder' => $folders->firstWhere('slug', 'trash'),
        ]);
    }

    public function reply(Request $request, PrivateConversation $conversation): RedirectResponse
    {
        $sender = $request->user();

        if (! $sender instanceof User) {
            return $this->toLogin($request);
        }

        $this->participantOrFail($sender, $conversation);

        $validated = $request->validateWithBag('reply', ['body' => ['required', 'string', 'max:20000']]);

        $others = $conversation->participants()->whereNull('deleted_at')->where('user_id', '!=', $sender->id)->with('user')->get()->map(fn ($participant) => $participant->user)->filter(fn ($other): bool => $other instanceof User);
        $blocked = $this->blockedAmong($sender, $others);

        if ($blocked->isNotEmpty()) {
            return back()->withErrors(['body' => __('private_messages.errors.blocked_recipients', ['users' => $blocked->implode(', ')])], 'reply');
        }

        try {
            $this->messages->reply($sender, $conversation, (string) $validated['body']);
        } catch (MessageRefused $refused) {
            return back()->withErrors(['body' => $refused->getMessage()], 'reply');
        }

        return redirect()->route('messages.show', $conversation)->with('message_status', __('private_messages.flash.reply_sent'));
    }

    public function move(Request $request, PrivateConversation $conversation): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $folders = $this->mailbox->foldersFor($user);
        $validated = $request->validate(['folder_id' => ['required', 'integer']]);
        $target = $folders->first(fn (PrivateMessageFolder $folder): bool => $folder->id === (int) $validated['folder_id']);

        abort_unless($target instanceof PrivateMessageFolder, 422);
        $this->participantOrFail($user, $conversation);

        $target->slug === 'trash'
            ? $this->mailbox->trash($user, [(int) $conversation->id])
            : $this->mailbox->move($user, [(int) $conversation->id], $target);

        return redirect()->route('messages.index', ['folder' => $target->slug])->with('message_status', __('private_messages.flash.conversation_moved'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $folders = $this->mailbox->foldersFor($user);

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(self::BULK_ACTIONS)],
            'conversation_ids' => ['required', 'array', 'min:1'],
            'conversation_ids.*' => ['integer'],
            'folder_id' => ['required_if:action,move', 'nullable', 'integer'],
            'return_folder' => ['nullable', 'string', 'max:80'],
        ], [
            'conversation_ids.required' => __('private_messages.errors.no_conversations_selected'),
            'conversation_ids.min' => __('private_messages.errors.no_conversations_selected'),
        ]);

        $ids = array_map('intval', $validated['conversation_ids']);
        $returnTo = (string) ($validated['return_folder'] ?? 'inbox');
        $returnTo = $folders->contains(fn (PrivateMessageFolder $folder): bool => $folder->slug === $returnTo) ? $returnTo : 'inbox';

        $done = fn (string $key, int $count): RedirectResponse => redirect()
            ->route('messages.index', ['folder' => $returnTo])
            ->with('message_status', trans_choice("private_messages.flash.{$key}", $count, ['count' => $count]));

        return match ($validated['action']) {
            'move' => $this->moveMany($user, $folders, $ids, (int) $validated['folder_id'], $done),
            'trash' => $done('conversations_trashed', $this->mailbox->trash($user, $ids)),
            'restore' => $done('conversations_restored', $this->mailbox->restore($user, $ids)),
            'delete' => $done('conversations_deleted', $this->mailbox->delete($user, $ids)),
            'mark_read' => $done('marked_read', $this->mailbox->markRead($user, $ids)),
            'mark_unread' => $done('marked_unread', $this->mailbox->markUnread($user, $ids)),
        };
    }

    public function emptyTrash(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $this->mailbox->foldersFor($user);
        $count = $this->mailbox->emptyTrash($user);

        return redirect()->route('messages.index', ['folder' => 'trash'])->with('message_status', trans_choice('private_messages.flash.trash_emptied', $count, ['count' => $count]));
    }

    public function createFolder(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);

        try {
            $folder = $this->mailbox->createFolder($user, (string) $validated['name']);
        } catch (MessageRefused $refused) {
            return back()->withErrors(['name' => $refused->getMessage()]);
        }

        return redirect()->route('messages.index', ['folder' => $folder->slug])->with('message_status', __('private_messages.flash.folder_created'));
    }

    public function deleteFolder(Request $request, PrivateMessageFolder $folder): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        abort_unless((int) $folder->user_id === (int) $user->id, 403);

        try {
            $this->mailbox->deleteFolder($user, $folder);
        } catch (MessageRefused) {
            abort(422);
        }

        return redirect()->route('messages.index', ['folder' => 'inbox'])->with('message_status', __('private_messages.flash.folder_deleted'));
    }

    /**
     * @param  list<int>  $ids
     */
    private function moveMany(User $user, Collection $folders, array $ids, int $folderId, \Closure $done): RedirectResponse
    {
        $target = $folders->first(fn (PrivateMessageFolder $folder): bool => $folder->id === $folderId);

        abort_unless($target instanceof PrivateMessageFolder, 422);

        return $target->slug === 'trash'
            ? $done('conversations_trashed', $this->mailbox->trash($user, $ids))
            : $done('conversations_moved', $this->mailbox->move($user, $ids, $target));
    }

    private function participantOrFail(User $user, PrivateConversation $conversation): PrivateConversationParticipant
    {
        $participant = Models::class('participant')::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($participant instanceof PrivateConversationParticipant, 403);

        return $participant;
    }

    /**
     * The usernames, among these, the sender may not write to: those who take no private messages and
     * those blocked either way.
     *
     * @param  iterable<User>  $recipients
     * @return Collection<int, string>
     */
    private function blockedAmong(User $sender, iterable $recipients): Collection
    {
        return collect($recipients)
            ->filter(fn (User $recipient): bool => ! $recipient->allowsPrivateMessages() || $this->ignored->isBlockedBetween($sender, $recipient))
            ->pluck('username')
            ->filter()
            ->values();
    }

    private function toLogin(Request $request): RedirectResponse
    {
        return redirect()->route('home', ['auth' => 'login', 'intended' => $request->getRequestUri()]);
    }
}
