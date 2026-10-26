<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\Forums\Enums\AttachmentRefusal;
use Filagora\Forums\Exceptions\AttachmentRefused;
use Filagora\Forums\Models\PostAttachment;
use Filagora\Forums\Services\Attachments;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves post attachments: the thread's attachment list, a file by id or by the old forum's
 * attachment id (those URLs are in years of posts and must keep working), and a thumbnail. Who may
 * see a file is `Attachments`'s answer, which follows the post it belongs to.
 */
class PostAttachmentController extends Controller
{
    public function __construct(
        private readonly Attachments $attachments,
        private readonly ForumAccess $access,
    ) {}

    /**
     * Stores an image pasted or dropped into the editor before its post exists. The editor puts the
     * returned URL in the body; saving the post then claims the file.
     */
    public function storeInline(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        try {
            $attachment = $this->attachments->storeInline($user, $validated['image']);
        } catch (AttachmentRefused $refusal) {
            throw ValidationException::withMessages(['image' => $refusal->getMessage()]);
        }

        return response()->json([
            'attachment_id' => $attachment->id,
            'url' => route('post.attachments.show', ['attachment' => $attachment->id, 'disposition' => 'inline']),
        ]);
    }

    public function index(Thread $thread, Request $request): View
    {
        $thread->loadMissing(['forum', 'author:id,username,name,email']);

        $user = $request->user() instanceof User ? $request->user() : null;

        abort_if($thread->forum === null, 404);
        abort_unless($this->access->canViewForum($thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($thread, $user), 403);

        $attachments = $this->attachments->visibleInThread($thread, $user);

        return view('forum.attachments', [
            'thread' => $thread,
            'attachments' => $attachments,
            'totalAttachments' => $attachments->count(),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        if ($request->query->has('styleid')) {
            abort(410);
        }

        $legacyAttachmentId = (int) ($request->query('attachmentid') ?? $request->query('id') ?? 0);
        abort_if($legacyAttachmentId <= 0, 404);

        $attachment = Models::class('post_attachment')::query()
            ->where('legacy_attachment_id', $legacyAttachmentId)
            ->first();

        abort_if($attachment === null, 404);

        return $this->respond($attachment, $request);
    }

    public function show(PostAttachment $attachment, Request $request): StreamedResponse
    {
        return $this->respond($attachment, $request);
    }

    public function thumbnail(PostAttachment $attachment, Request $request): StreamedResponse
    {
        abort_if($attachment->thumbnail_storage_path === null, 404);

        return $this->respond($attachment, $request, thumbnail: true);
    }

    private function respond(PostAttachment $attachment, Request $request, bool $thumbnail = false): StreamedResponse
    {
        $user = $request->user() instanceof User ? $request->user() : null;

        if (! $this->attachments->canView($attachment, $user)) {
            abort($this->denialStatus($attachment, $user));
        }

        try {
            return $this->attachments->stream(
                $attachment,
                thumbnail: $thumbnail,
                inline: $request->query('disposition') === 'inline',
            );
        } catch (AttachmentRefused $refusal) {
            abort($refusal->reason === AttachmentRefusal::FILE_MISSING ? 404 : 403);
        }
    }

    /**
     * Why a file is refused: 403 when the forum or thread it sits in is closed to the viewer (or
     * nobody has claimed it and it is not theirs), 404 when only its post is hidden from them, so a
     * hidden post's files are not confirmed to exist.
     */
    private function denialStatus(PostAttachment $attachment, ?User $user): int
    {
        $thread = $attachment->thread_id !== null
            ? Models::class('thread')::query()->with('forum')->find($attachment->thread_id)
            : null;

        if ($thread === null || $thread->forum === null) {
            return $attachment->post_id === null ? 403 : 404;
        }

        if (! $this->access->canViewForum($thread->forum, $user) || ! $this->access->canAccessThread($thread, $user)) {
            return 403;
        }

        return 404;
    }
}
