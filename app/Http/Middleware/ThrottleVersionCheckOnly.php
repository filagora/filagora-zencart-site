<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the version check's limiter to `downloads.php` only when it is asked for the version
 * check; the other modes of that script just redirect and are left alone.
 */
class ThrottleVersionCheckOnly
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $request->query('do') === 'versioncheck'
            ? $this->throttle->handle($request, $next, 'legacy-plugin-check')
            : $next($request);
    }
}
