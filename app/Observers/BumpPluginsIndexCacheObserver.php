<?php

namespace App\Observers;

use App\Support\PluginsIndexCache;

class BumpPluginsIndexCacheObserver
{
    public function saved(mixed $model): void
    {
        PluginsIndexCache::bumpVersion();
    }

    public function deleted(mixed $model): void
    {
        PluginsIndexCache::bumpVersion();
    }

    public function restored(mixed $model): void
    {
        PluginsIndexCache::bumpVersion();
    }
}
