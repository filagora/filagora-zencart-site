<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureBannedDashboardUserIsLoggedOut
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($this->isBannedUser($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home');
        }

        return $next($request);
    }

    private function isBannedUser(mixed $user): bool
    {
        return is_object($user)
            && method_exists($user, 'isCurrentlyBanned')
            && $user->isCurrentlyBanned();
    }
}
