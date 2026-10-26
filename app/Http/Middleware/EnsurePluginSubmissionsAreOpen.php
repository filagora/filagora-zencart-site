<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kill switch for plugin and release uploads (PLUGINS_SUBMISSIONS_ENABLED=false).
 * Browsing, downloads and the version-check endpoints are unaffected.
 */
class EnsurePluginSubmissionsAreOpen
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('plugins.submissions_enabled', true)) {
            return $next($request);
        }

        abort(503, 'Plugin submissions are temporarily paused. Please try again later.');
    }
}
