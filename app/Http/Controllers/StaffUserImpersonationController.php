<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UserImpersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff acting as another member to see what they see, and going back.
 */
class StaffUserImpersonationController extends Controller
{
    public function start(Request $request, User $user, UserImpersonation $impersonation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_if($impersonation->active($request), 403);
        Gate::forUser($actor)->authorize('impersonate', $user);

        $impersonation->start($request, $actor, $user);

        return $this->back($request);
    }

    public function stop(Request $request, UserImpersonation $impersonation): RedirectResponse
    {
        abort_unless($request->user() instanceof User && $impersonation->active($request), 403);
        abort_unless($impersonation->stop($request) instanceof User, 403);

        return $this->back($request);
    }

    private function back(Request $request): RedirectResponse
    {
        $to = (string) $request->input('redirect_to', '');

        return redirect()->to($to !== '' && str_starts_with($to, '/') && ! str_starts_with($to, '//') && (string) parse_url($to, PHP_URL_PATH) !== '' ? $to : route('home'));
    }
}
