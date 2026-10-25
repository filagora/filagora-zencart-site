<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets ?search_icon_toggle=1|0 flip the header's search-icon-toggle
 * behavior (docs/homepage-feedback-fixes-plan.md, Fix 5) for the current
 * session, without changing the profile setting itself - handy for
 * previewing either state regardless of what's saved. The override, once
 * set, persists across requests until explicitly changed again; it takes
 * precedence over the user's own saved preference (see
 * AppServiceProvider's global view composer).
 */
class ApplySearchIconToggleOverride
{
    public const SESSION_KEY = 'search_icon_toggle_override';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->has('search_icon_toggle')) {
            $request->session()->put(self::SESSION_KEY, $request->boolean('search_icon_toggle'));
        }

        return $next($request);
    }
}
