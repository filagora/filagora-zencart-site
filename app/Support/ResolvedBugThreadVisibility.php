<?php

namespace App\Support;

use App\Models\User;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Http\Request;

/**
 * A moderator's forum-listing choice: when on, threads whose bug reports are all closed out
 * (completed, feature request, won't fix) are left out of a forum's thread list. Threads with no bug
 * report, or with one still open, are not affected. Off by default and only for moderators.
 */
class ResolvedBugThreadVisibility
{
    public const SESSION_KEY = 'moderator_hide_resolved_bug_threads';

    public function __construct(private readonly ForumAccess $access) {}

    public function availableFor(?User $user): bool
    {
        return $user instanceof User && $this->access->canModerate($user);
    }

    public function hiddenFor(Request $request, ?User $user): bool
    {
        return $this->availableFor($user)
            && $request->hasSession()
            && filter_var($request->session()->get(self::SESSION_KEY, false), FILTER_VALIDATE_BOOL);
    }

    public function setHidden(Request $request, bool $hidden): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $hidden);
        }
    }
}
