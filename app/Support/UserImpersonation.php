<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class UserImpersonation
{
    public const SESSION_ORIGINAL_USER_ID = 'user_impersonation.original_user_id';

    public const SESSION_ORIGINAL_USERNAME = 'user_impersonation.original_username';

    public function active(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $originalUserId = $this->originalUserId($request);
        $currentUserId = $request->user()?->getAuthIdentifier();

        return $originalUserId !== null
            && $currentUserId !== null
            && $originalUserId !== (int) $currentUserId;
    }

    public function originalUserId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $originalUserId = $request->session()->get(self::SESSION_ORIGINAL_USER_ID);

        return is_numeric($originalUserId) ? (int) $originalUserId : null;
    }

    public function originalUsername(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $originalUsername = $request->session()->get(self::SESSION_ORIGINAL_USERNAME);

        return is_string($originalUsername) && $originalUsername !== ''
            ? $originalUsername
            : null;
    }

    public function start(Request $request, User $actor, User $target): void
    {
        Auth::guard('web')->login($target);

        if (! $request->hasSession()) {
            return;
        }

        $request->session()->put([
            self::SESSION_ORIGINAL_USER_ID => $actor->getKey(),
            self::SESSION_ORIGINAL_USERNAME => $actor->username ?: $actor->name ?: (string) $actor->getKey(),
        ]);
        $request->session()->regenerateToken();

        app(StaffFrontendPreview::class)->setEnabled($request, true);
    }

    public function stop(Request $request): ?User
    {
        $originalUserId = $this->originalUserId($request);
        if ($originalUserId === null) {
            return null;
        }

        $originalUser = User::query()->find($originalUserId);
        if (! $originalUser instanceof User) {
            return null;
        }

        Auth::guard('web')->login($originalUser);

        if ($request->hasSession()) {
            $request->session()->forget([
                self::SESSION_ORIGINAL_USER_ID,
                self::SESSION_ORIGINAL_USERNAME,
            ]);
            $request->session()->regenerateToken();
        }

        app(StaffFrontendPreview::class)->setEnabled($request, true);

        return $originalUser;
    }

    public function clear(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget([
            self::SESSION_ORIGINAL_USER_ID,
            self::SESSION_ORIGINAL_USERNAME,
        ]);
    }
}
