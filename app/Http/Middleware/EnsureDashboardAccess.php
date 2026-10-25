<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filagora\Core\Contracts\AccessChecker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('home');
        }

        if ($this->isBannedUser($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home');
        }

        if ($request->routeIs('filament.dashboard.auth.email-verification.prompt')) {
            $intended = $request->session()->get('url.intended');

            return redirect()->route('verification.notice', array_filter([
                'intended' => is_string($intended) && $intended !== '' ? $intended : null,
            ]));
        }

        if ($request->routeIs('filament.dashboard.auth.email-verification.verify')) {
            return $next($request);
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return redirect()->route('filament.dashboard.auth.email-verification.prompt');
        }

        $canAccessDashboard = $user instanceof User
            && app(AccessChecker::class)->allows($user, 'operator.dashboard.access');

        if (! $canAccessDashboard) {
            return redirect()->route('home');
        }

        return $next($request);
    }

    private function isBannedUser(mixed $user): bool
    {
        return method_exists($user, 'isCurrentlyBanned')
            && $user->isCurrentlyBanned();
    }
}
