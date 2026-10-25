<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePostRequest;
use App\Models\User;
use App\Support\PollValidationRules;
use Filagora\Forums\Enums\PostingDenial;
use Filagora\Forums\Exceptions\AttachmentRefused;
use Filagora\Forums\Exceptions\PostingNotPermitted;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Services\Attachments;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Services\Polls;
use Filagora\Forums\Services\Posting;
use Filagora\Forums\Services\Subscriptions;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * What a signed-in member does to a thread: start one, reply, edit a post, follow or unfollow.
 * The rules (who may post where, approval, the duplicate window, the edit window) belong to the
 * forums package's `Posting`; this reads the request, saves attachments, and decides where to go.
 */
class ThreadPostingController extends Controller
{
    private const ATTACHMENT_RULES = ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,zip,log'];

    public function __construct(
        private readonly Posting $posting,
        private readonly ForumAccess $access,
        private readonly Attachments $attachments,
        private readonly Subscriptions $subscriptions,
        private readonly Polls $polls,
    ) {}

    public function newThreadForm(Request $request, Forum $forum): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin(route('threads.create.form', $forum));
        }

        abort_unless($this->access->canViewForum($forum, $user), 403);
        abort_unless($this->access->canPostInForum($forum, $user), 403);

        $actsAsStaff = $this->access->actsAsStaff($user);

        return view('thread.new', [
            'forum' => $forum,
            'canCreateThreadAsUnapproved' => $actsAsStaff,
            'canCreatePollWithThread' => $actsAsStaff,
            'canLockThreadOnCreate' => $actsAsStaff,
        ]);
    }

    public function storeThread(Request $request, Forum $forum): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin(route('threads.create.form', $forum));
        }

        $actsAsStaff = $this->access->actsAsStaff($user);

        abort_unless($this->access->canViewForum($forum, $user), 403);
        abort_unless($this->access->canPostInForum($forum, $user), 403);

        $validated = $request->validate(array_merge([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'post_as_unapproved' => ['nullable', 'boolean'],
            'lock_thread' => ['nullable', 'boolean'],
            'subscribe' => ['nullable', 'boolean'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => self::ATTACHMENT_RULES,
            'create_poll' => ['nullable', 'boolean'],
        ], $actsAsStaff ? PollValidationRules::optional() : []));

        try {
            $created = $this->posting->startThread(
                $user,
                $forum,
                $validated['title'],
                $validated['body'],
                postAsUnapproved: $request->boolean('post_as_unapproved'),
                lockThread: $request->boolean('lock_thread'),
                subscribeAuthor: $request->has('subscribe') ? $request->boolean('subscribe') : true,
            );
        } catch (PostingNotPermitted $refusal) {
            if ($refusal->reason === PostingDenial::AWAITING_FIRST_REVIEW) {
                return redirect()
                    ->route('threads.create.form', $forum)
                    ->with('thread_status', __('forum.errors.awaiting_first_post_review'));
            }

            abort(403);
        }

        if ($created->duplicate) {
            return redirect()
                ->to(route('threads.show', $created->thread).'#post-'.$created->post->id)
                ->with('thread_status', __('forum.flash.thread_duplicate'));
        }

        $this->saveAttachments($created->post, $user, $request, $validated['body']);

        // $actsAsStaff gated whether poll fields were validated at all, so a non-staff submission
        // of create_poll=1 never reaches here with poll_questions present.
        if ($actsAsStaff && $request->boolean('create_poll')) {
            $this->polls->create($user, $created->thread, $validated['poll_questions']);
        }

        return redirect()
            ->to(route('threads.show', $created->thread).'#post-'.$created->post->id)
            ->with('thread_status', $created->requiresApproval ? __('forum.flash.thread_submitted') : __('forum.flash.thread_created'));
    }

    public function reply(Request $request, Thread $thread): RedirectResponse
    {
        $thread->loadMissing('forum');
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin(route('threads.show', $thread));
        }

        abort_unless($this->access->canViewForum($thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($thread, $user), 403);

        $validated = $request->validateWithBag('reply', [
            'body' => ['required', 'string', 'max:20000'],
            'subscribe' => ['nullable', 'boolean'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => self::ATTACHMENT_RULES,
        ]);

        try {
            $created = $this->posting->reply($user, $thread, $validated['body']);
        } catch (PostingNotPermitted $refusal) {
            return match ($refusal->reason) {
                PostingDenial::FORUM_NOT_VISIBLE, PostingDenial::THREAD_NOT_ACCESSIBLE => abort(403),
                PostingDenial::AWAITING_FIRST_REVIEW => $this->replyError($thread, __('forum.errors.awaiting_first_post_review')),
                default => $this->replyError($thread, __('forum.errors.reply_not_permitted')),
            };
        }

        if ($created->duplicate) {
            return redirect()
                ->to($this->postUrl($thread, $created->post, $user))
                ->with('thread_status', __('forum.flash.reply_duplicate'));
        }

        $this->saveAttachments($created->post, $user, $request, trim($validated['body']));

        // Without a choice on the form, the package has already followed the thread or not from the
        // author's standing preference; a ticked or unticked box overrides that for this post.
        if ($request->has('subscribe')) {
            $request->boolean('subscribe')
                ? $this->subscriptions->subscribe($user, $thread)
                : $this->subscriptions->unsubscribe($user, $thread);
        }

        return redirect()
            ->to($this->postUrl($thread, $created->post, $user))
            ->with('thread_status', $created->requiresApproval ? __('forum.flash.reply_submitted') : __('forum.flash.reply_posted'));
    }

    public function editPostForm(Request $request, Post $post): View
    {
        $user = $request->user() instanceof User ? $request->user() : null;
        $post->loadMissing('thread.forum');

        abort_unless($this->access->canEditPost($post, $user), 403);

        $post->loadMissing(['thread.forum.group', 'author:id,username,name,email', 'editedBy:id,username,name,email', 'attachments']);

        return view('post.edit', [
            'post' => $post,
            'thread' => $post->thread,
            'editorBody' => (string) $post->body,
            'returnTo' => $this->returnTarget($post, (string) $request->query('return_to', '')),
        ]);
    }

    public function updatePost(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $user = $request->user();
        $post->loadMissing('thread.forum');

        abort_unless($user instanceof User, 403);

        try {
            $this->posting->editPost($user, $post, (string) $request->validated('body'));
        } catch (PostingNotPermitted) {
            abort(403);
        }

        $removeIds = array_map('intval', (array) $request->validated('remove_attachments', []));

        if ($removeIds !== []) {
            $post->attachments()->whereIn('id', $removeIds)->get()->each(fn ($attachment) => $this->attachments->delete($attachment));
        }

        $this->saveAttachments($post, $user, $request, trim((string) $request->validated('body')));

        return redirect()
            ->to($this->returnTarget($post, (string) $request->validated('return_to', '')))
            ->with('thread_status', __('forum.flash.post_updated'));
    }

    public function subscribe(Request $request, Thread $thread): RedirectResponse
    {
        $thread->loadMissing('forum');
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin(route('threads.show', $thread));
        }

        abort_unless($this->access->canViewForum($thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($thread, $user), 403);

        $this->subscriptions->subscribe($user, $thread);

        return $this->afterSubscription($request, $thread, __('forum.flash.thread_subscribed'));
    }

    public function unsubscribe(Request $request, Thread $thread): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('home');
        }

        $this->subscriptions->unsubscribe($user, $thread);

        if ((string) $request->input('redirect_to') === 'profile') {
            return redirect()
                ->route('profile.show', ['tab' => 'subscriptions'])
                ->with('profile_status', __('profile.flash.subscription_removed'));
        }

        return $this->afterSubscription($request, $thread, __('forum.flash.thread_unsubscribed'));
    }

    /**
     * Stores the uploaded files and claims the pasted images the body refers to. A refused file
     * (the hourly limit) comes back as a validation error on the attachments field.
     */
    private function saveAttachments(Post $post, User $user, Request $request, string $body): void
    {
        try {
            $this->attachments->store($post, $user, $request->file('attachments', []));
            $this->attachments->claimInline($body, $user, $post);
        } catch (AttachmentRefused $refusal) {
            throw ValidationException::withMessages(['attachments' => $refusal->getMessage()]);
        }
    }

    private function toLogin(string $intended): RedirectResponse
    {
        return redirect()->route('home', ['auth' => 'login', 'intended' => $intended]);
    }

    private function replyError(Thread $thread, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('threads.show', $thread).'#reply')
            ->withErrors(['body' => $message], 'reply');
    }

    /**
     * The paginated, anchored URL of a post in its thread, as this viewer sees the thread.
     */
    private function postUrl(Thread $thread, Post $post, User $user): string
    {
        $position = $this->access->scopeVisiblePosts(
            $post::query()->where('thread_id', $thread->id),
            $user,
        )->where('id', '<=', $post->id)->count();

        $page = max(1, (int) ceil($position / ForumController::THREAD_POSTS_PER_PAGE));

        return route('threads.show', ['thread' => $thread, 'page' => $page]).'#post-'.$post->id;
    }

    private function returnTarget(Post $post, string $returnTo): string
    {
        $sanitized = $this->internalPath($returnTo);

        if ($sanitized !== '') {
            return str_contains($sanitized, '#post-') ? $sanitized : $sanitized.'#post-'.$post->id;
        }

        return route('threads.show', $post->thread_id).'#post-'.$post->id;
    }

    private function afterSubscription(Request $request, Thread $thread, string $message): RedirectResponse
    {
        $returnTo = $this->internalPath((string) $request->input('return_to', ''));

        return redirect()
            ->to($returnTo !== '' ? $returnTo : route('threads.show', $thread))
            ->with('thread_status', $message);
    }

    /**
     * Same-origin paths only: a single leading "/" not followed by "/" or "\\" (both of which
     * browsers resolve as a protocol-relative absolute URL, an open redirect), and no control
     * characters, since browsers strip TAB, CR and LF before parsing a Location and "/<TAB>/evil.com"
     * would then become "//evil.com".
     */
    private function internalPath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === ''
            || preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1
            || preg_match('#^/[^/\\\\]#', $trimmed) !== 1) {
            return '';
        }

        return $trimmed;
    }
}
