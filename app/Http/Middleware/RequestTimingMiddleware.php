<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestTimingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabledFor($request)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $startedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;

        DB::listen(static function ($query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += (float) ($query->time ?? 0.0);
        });

        /** @var Response $response */
        $response = $next($request);

        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
        $queryTimeMs = round($queryTimeMs, 2);
        $peakMemoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $route = $request->route();
        $routeName = is_object($route) ? $route->getName() : null;

        Log::channel(config('app.request_timing.channel'))->info('request.timing', [
            'method' => $request->method(),
            'route_name' => $routeName,
            'status' => $response->getStatusCode(),
            'status_class' => intdiv($response->getStatusCode(), 100).'xx',
            'duration_ms' => $durationMs,
            'db_query_count' => $queryCount,
            'db_query_time_ms' => $queryTimeMs,
            'peak_memory_mb' => $peakMemoryMb,
        ]);

        $response->headers->set('X-Request-Duration-Ms', (string) $durationMs);
        $response->headers->set('X-Db-Query-Count', (string) $queryCount);
        $response->headers->set('X-Db-Query-Time-Ms', (string) $queryTimeMs);
        $response->headers->set('Server-Timing', sprintf('app;dur=%s, db;dur=%s', $durationMs, $queryTimeMs));

        return $response;
    }

    private function enabledFor(Request $request): bool
    {
        if (! (bool) config('app.request_timing.enabled', false)) {
            return false;
        }

        $patterns = config('app.request_timing.paths', []);
        if (! is_array($patterns) || $patterns === []) {
            return true;
        }

        $path = ltrim($request->path(), '/');

        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '' || $pattern === '*') {
                return true;
            }

            $normalizedPattern = ltrim($pattern, '/');
            if ($path === $normalizedPattern || str_starts_with($path, $normalizedPattern.'/')) {
                return true;
            }
        }

        return false;
    }
}
