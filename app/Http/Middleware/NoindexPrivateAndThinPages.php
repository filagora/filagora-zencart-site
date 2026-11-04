<?php

namespace App\Http\Middleware;

use Closure;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\Thread;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps pages that are private, personalised or thin out of search results.
 *
 * This is a header rather than a meta tag so it also covers the Filament
 * dashboard and Livewire-driven pages that don't use the main layout. It is
 * deliberately paired with *not* disallowing these paths in robots.txt for
 * the pages that should be dropped from the index: a crawler can only honour
 * a noindex on a page it is allowed to fetch.
 */
class NoindexPrivateAndThinPages
{
    /** @var list<string> */
    private const PATTERNS = [
        'dashboard',
        'dashboard/*',
        'login',
        'register',
        'password/*',
        'verify-email',
        'email-preferences/*',
        'account/*',
        'profile',
        'profile/*',
        'messages',
        'messages/*',
        'notifications',
        'notifications/*',
        'ai-chat',
        'ai-chat/*',
        'staff/*',
        'search',
        'members',
        'members/*',
        'search.php',
        'private.php*',
        'newreply.php*',
        'sendmessage.php*',
        'member.php*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('X-Robots-Tag') && ($request->is(self::PATTERNS) || $this->isInNoindexForum($request))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * Staff can flag a whole forum noindex; its page and its threads follow.
     * Runs after the controller, so a thread's forum relation is already loaded.
     */
    private function isInNoindexForum(Request $request): bool
    {
        $route = $request->route();

        return match ($route?->getName()) {
            'forums.show' => $route->parameter('forum') instanceof Forum && $route->parameter('forum')->noindex,
            'threads.show' => $route->parameter('thread') instanceof Thread && $route->parameter('thread')->forum?->noindex === true,
            default => false,
        };
    }
}
