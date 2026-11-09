<?php

namespace Tests\Feature;

use Filagora\Search\Support\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A deploy's optimize:clear FLUSHDBs the cache connection. The search circuit breaker describes the
 * engine, not the database, so it lives in the `durable` store (CACHE_DURABLE_STORE=durable in
 * production, as on the old site).
 */
class DurableCacheStoreTest extends TestCase
{
    public function test_the_durable_store_is_defined_off_the_flushed_cache_connection(): void
    {
        $store = config('cache.stores.durable');

        $this->assertSame('redis', $store['driver']);
        $this->assertNotSame(config('cache.stores.redis.connection'), $store['connection']);
    }

    public function test_the_search_circuit_follows_the_durable_store(): void
    {
        config(['cache.stores.durable' => ['driver' => 'array']]);
        $config = $this->circuitConfigWith('durable');
        $this->assertSame('durable', $config['store']);
        config(['filagora-search.circuit' => $config]);

        (new CircuitBreaker)->trip();

        $this->assertTrue(Cache::store('durable')->get('filagora-search:circuit-open'));
        $this->assertNull(Cache::store('array')->get('filagora-search:circuit-open'));
    }

    public function test_the_search_circuit_uses_the_default_store_when_no_durable_store_is_set(): void
    {
        $this->assertNull($this->circuitConfigWith('')['store']);
    }

    /**
     * @return array{cooldown_seconds: int, store: ?string}
     */
    private function circuitConfigWith(string $durable): array
    {
        $previous = getenv('CACHE_DURABLE_STORE');
        putenv('CACHE_DURABLE_STORE='.$durable);
        $_ENV['CACHE_DURABLE_STORE'] = $durable;
        $_SERVER['CACHE_DURABLE_STORE'] = $durable;

        try {
            return (require config_path('filagora-search.php'))['circuit'];
        } finally {
            putenv($previous === false ? 'CACHE_DURABLE_STORE' : 'CACHE_DURABLE_STORE='.$previous);
            unset($_ENV['CACHE_DURABLE_STORE'], $_SERVER['CACHE_DURABLE_STORE']);
        }
    }
}
