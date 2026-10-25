<?php

namespace App\Support;

use App\Models\User;
use Filagora\Core\Contracts\AccessChecker;
use Illuminate\Http\Request;

class StaffFrontendPreview
{
    /**
     * True means moderation controls are shown to the staff member on the
     * frontend; false means they're previewing the site as a normal member
     * would see it. Defaults to true (shown) when the session key is absent,
     * so staff see moderation controls until they deliberately hide them.
     */
    public const SESSION_KEY = 'staff_show_moderation_controls';

    public function availableFor(?User $user): bool
    {
        return $user instanceof User
            && app(AccessChecker::class)->allows($user, 'operator.forum.moderate');
    }

    public function enabledFor(Request $request, ?User $user): bool
    {
        // A user who can't toggle this preference at all (no operator.forum.moderate,
        // e.g. a plugin-only moderator, or no session to read one from) has no way to
        // hide moderation controls in the first place, so treat them as shown rather
        // than silently hiding moderation UI gated on a *different* entitlement (like
        // operator.plugin.moderate) that this preference was never meant to affect.
        if (! $this->availableFor($user) || ! $request->hasSession()) {
            return true;
        }

        return filter_var($request->session()->get(self::SESSION_KEY, true), FILTER_VALIDATE_BOOL);
    }

    public function setEnabled(Request $request, bool $enabled): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $enabled);
    }
}
