<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CacheMetrics
{
    /**
     * Sentinel returned when a key is absent, so a falsy cached value is not
     * mistaken for a miss on a single read.
     */
    private static ?object $missSentinel = null;

    /**
     * The cache is never allowed to take a page down. A Redis restart (e.g.
     * after a reboot) answers every command with "LOADING Redis is loading
     * the dataset in memory" for a few seconds, and a connection failure is
     * always possible regardless of cause - either way, callers fall back to
     * computing the value directly rather than letting the exception bubble
     * up. See docs/deployment.md: "the cache is the one thing that is safe
     * to lose"; that has to hold for a transient failure too, not just an
     * intentional flush.
     */
    public static function remember(string $namespace, string $key, mixed $ttl, Closure $resolver): mixed
    {
        // One round trip, not two. The previous Cache::has() + Cache::get()
        // pair cost an EXISTS *and* a GET on every hit, which on the
        // entitlement path multiplied into hundreds of Redis calls per
        // request.
        //
        // Note a cached null still reads as a miss and is rebuilt: the cache
        // Repository turns a stored null into the supplied default, so no
        // sentinel can tell the two apart. That matches Laravel's own
        // Cache::remember(); cache values that may be null need their own
        // wrapper.
        $sentinel = self::missSentinel();

        try {
            $value = Cache::get($key, $sentinel);
        } catch (Throwable $exception) {
            self::recordUnavailable($namespace, $key, $exception);

            return $resolver();
        }

        if ($value !== $sentinel) {
            self::record($namespace, 'hit', $key);

            return $value;
        }

        self::record($namespace, 'miss', $key);
        $value = $resolver();

        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable $exception) {
            self::recordUnavailable($namespace, $key, $exception);
        }

        return $value;
    }

    private static function missSentinel(): object
    {
        return self::$missSentinel ??= new \stdClass;
    }

    public static function hit(string $namespace, string $key): void
    {
        self::record($namespace, 'hit', $key);
    }

    public static function miss(string $namespace, string $key): void
    {
        self::record($namespace, 'miss', $key);
    }

    private static function record(string $namespace, string $event, string $key): void
    {
        if (! self::enabled()) {
            return;
        }

        Log::info('cache.metric', [
            'namespace' => $namespace,
            'event' => $event,
            'key' => $key,
        ]);
    }

    /**
     * Unlike record(), this always logs regardless of CACHE_METRICS_ENABLED:
     * a cache outage is an operational signal worth keeping, not opt-in
     * metrics noise.
     */
    private static function recordUnavailable(string $namespace, string $key, Throwable $exception): void
    {
        Log::warning('cache.unavailable', [
            'namespace' => $namespace,
            'key' => $key,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private static function enabled(): bool
    {
        return (bool) env('CACHE_METRICS_ENABLED', false);
    }
}
