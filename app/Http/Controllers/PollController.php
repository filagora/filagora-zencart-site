<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModeratePollRequest;
use App\Http\Requests\StorePollRequest;
use App\Http\Requests\SubmitPollVoteRequest;
use App\Models\User;
use Filagora\Forums\Enums\PollRefusal;
use Filagora\Forums\Exceptions\PollRefused;
use Filagora\Forums\Models\Poll;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Services\Polls;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Polls on a thread: moderators add, edit (until someone has voted), close and reopen them, and any
 * member who can see the thread votes once. The rules and the moderation log are the forums
 * package's `Polls`.
 */
class PollController extends Controller
{
    public function __construct(
        private readonly Polls $polls,
        private readonly ForumAccess $access,
    ) {}

    public function createForm(Thread $thread): View|RedirectResponse
    {
        $this->moderator();

        $thread->loadMissing('poll');

        if ($thread->poll instanceof Poll) {
            return $this->toThread($thread, __('forum.errors.poll_already_exists'), anchor: false);
        }

        return view('poll.create', ['thread' => $thread]);
    }

    public function store(StorePollRequest $request, Thread $thread): RedirectResponse
    {
        $user = $this->moderator();

        try {
            $this->polls->create($user, $thread, $request->validated('poll_questions'));
        } catch (PollRefused $refusal) {
            if ($refusal->reason === PollRefusal::ALREADY_HAS_POLL) {
                return $this->toThread($thread, __('forum.errors.poll_already_exists'), anchor: false);
            }

            throw $refusal;
        }

        return $this->toThread($thread, __('forum.flash.poll_created'));
    }

    public function editForm(Poll $poll): View|RedirectResponse
    {
        $this->moderator();

        $poll->loadMissing(['thread', 'questions.options']);

        if ($poll->hasVotes()) {
            return $this->toThread($poll->thread, __('forum.errors.poll_has_votes'));
        }

        return view('poll.edit', ['poll' => $poll]);
    }

    public function update(StorePollRequest $request, Poll $poll): RedirectResponse
    {
        $user = $this->moderator();
        $poll->loadMissing('thread');

        try {
            $this->polls->replaceQuestions($user, $poll, $request->validated('poll_questions'));
        } catch (PollRefused $refusal) {
            if ($refusal->reason === PollRefusal::HAS_VOTES) {
                return $this->toThread($poll->thread, __('forum.errors.poll_has_votes'));
            }

            throw $refusal;
        }

        return $this->toThread($poll->thread, __('forum.flash.poll_updated'));
    }

    public function moderate(ModeratePollRequest $request, Poll $poll): RedirectResponse
    {
        $user = $this->moderator();
        $poll->loadMissing('thread');
        $action = (string) $request->validated('action');

        try {
            $action === 'close' ? $this->polls->close($user, $poll) : $this->polls->reopen($user, $poll);
        } catch (PollRefused $refusal) {
            // Closing a closed poll or reopening an open one changes nothing, as before.
            if ($refusal->reason !== PollRefusal::WRONG_STATE) {
                throw $refusal;
            }
        }

        return $this->toThread($poll->thread, __($action === 'close' ? 'forum.flash.poll_closed' : 'forum.flash.poll_reopened'));
    }

    public function vote(SubmitPollVoteRequest $request, Poll $poll): RedirectResponse
    {
        $poll->loadMissing('thread.forum');
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('home', [
                'auth' => 'login',
                'intended' => route('threads.show', $poll->thread),
            ]);
        }

        abort_unless($this->access->canViewForum($poll->thread->forum, $user), 403);
        abort_unless($this->access->canAccessThread($poll->thread, $user), 403);

        $answers = [];

        foreach ((array) $request->validated('poll_answers') as $questionId => $optionIds) {
            $answers[(int) $questionId] = array_map('intval', (array) $optionIds);
        }

        try {
            $this->polls->vote($user, $poll, $answers);
        } catch (PollRefused $refusal) {
            return match ($refusal->reason) {
                PollRefusal::CLOSED => $this->toThread($poll->thread, __('forum.errors.poll_closed')),
                PollRefusal::ALREADY_VOTED => $this->toThread($poll->thread, __('forum.errors.poll_already_voted')),
                PollRefusal::NOT_VISIBLE => abort(403),
                default => throw $refusal,
            };
        }

        return $this->toThread($poll->thread, __('forum.flash.poll_voted'));
    }

    /**
     * Only moderators manage polls.
     */
    private function moderator(): User
    {
        $user = request()->user();

        abort_unless($user instanceof User && $this->access->canModerate($user), 403);

        return $user;
    }

    private function toThread(Thread $thread, string $message, bool $anchor = true): RedirectResponse
    {
        return redirect()
            ->to(route('threads.show', $thread).($anchor ? '#poll' : ''))
            ->with('thread_status', $message);
    }
}
