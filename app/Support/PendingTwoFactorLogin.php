<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Reads the short-lived "step one passed, waiting on a 2FA code" marker
 * LoginController::store() writes to the session. Used by both the
 * controller itself and the site_header view composer, since the login
 * form partial is always present (hidden) on every page, not just /login.
 */
final class PendingTwoFactorLogin
{
    public const SESSION_KEY = 'login.two_factor';

    public static function resolve(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending)) {
            return null;
        }

        $expiresAt = (int) ($pending['expires_at'] ?? 0);
        if ($expiresAt < now()->timestamp) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $userId = $pending['user_id'] ?? null;
        $user = is_int($userId) || is_string($userId) ? User::find($userId) : null;

        if (! $user instanceof User) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $user;
    }
}
