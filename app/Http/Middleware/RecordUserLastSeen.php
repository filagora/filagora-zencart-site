<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordUserLastSeen
{
    private const THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->recordIfStale($user);
        }

        return $next($request);
    }

    private function recordIfStale(User $user): void
    {
        if ($user->last_seen_at !== null && $user->last_seen_at->gt(now()->subMinutes(self::THROTTLE_MINUTES))) {
            return;
        }

        User::query()->whereKey($user->id)->update(['last_seen_at' => now()]);
    }
}
