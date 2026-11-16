<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The version-check cache sync reaches the production cache unless the `.env` names another, so a
 * carried-over `.env` without the base URL does not switch sync off.
 */
class CacheSyncConfigTest extends TestCase
{
    public function test_the_cache_sync_points_at_the_production_cache_by_default(): void
    {
        $this->assertSame('https://plugins.zen-cart.com', config('filagora-zencart.cache_sync.base_url'));
    }

    public function test_the_cache_sync_is_off_until_it_is_switched_on(): void
    {
        $this->assertFalse((bool) config('filagora-zencart.cache_sync.enabled'));
    }
}
