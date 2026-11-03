<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddMonitoringContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = (string) config('observability.request_id.header', 'X-Request-ID');
        $requestId = $this->validRequestId($request->headers->get($headerName))
            ?? (string) Str::uuid();
        $previousSharedContext = Log::sharedContext();
        $previousSharedContext = is_array($previousSharedContext) ? $previousSharedContext : [];

        Log::shareContext(['request_id' => $requestId]);

        try {
            $response = $next($request);
            $response->headers->set($headerName, $requestId);

            return $response;
        } finally {
            Log::withoutContext(['request_id']);
            Log::flushSharedContext();

            if ($previousSharedContext !== []) {
                Log::shareContext($previousSharedContext);
            }
        }
    }

    private function validRequestId(?string $requestId): ?string
    {
        if ($requestId === null || $requestId === '') {
            return null;
        }

        if (strlen($requestId) > (int) config('observability.request_id.maximum_length', 128)) {
            return null;
        }

        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $requestId) === 1
            ? $requestId
            : null;
    }
}
