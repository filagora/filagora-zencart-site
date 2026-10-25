<?php

namespace App\Observers;

use App\Support\ForumsIndexCache;

class FlushForumsIndexCacheObserver
{
    public function saved(mixed $model): void
    {
        ForumsIndexCache::flushAll();
    }

    public function deleted(mixed $model): void
    {
        ForumsIndexCache::flushAll();
    }

    public function restored(mixed $model): void
    {
        ForumsIndexCache::flushAll();
    }
}
