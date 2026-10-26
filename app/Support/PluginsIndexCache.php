<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PluginsIndexCache
{
    private const VERSION_KEY = 'plugins:index:version:v1';

    public static function ttlSeconds(): int
    {
        return max(30, (int) config('plugins.index_cache_ttl_seconds', 300));
    }

    public static function currentVersion(): int
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);

        return $version > 0 ? $version : 1;
    }

    public static function bumpVersion(): int
    {
        $next = self::currentVersion() + 1;
        Cache::forever(self::VERSION_KEY, $next);

        return $next;
    }

    public static function resetVersion(): void
    {
        Cache::forever(self::VERSION_KEY, 1);
    }

    public static function keyFor(
        int|string|null $typeId,
        int $page,
        string $sort = 'date_desc',
        string $encapsulated = 'all'
    ): string {
        $typePart = $typeId === null ? 'all' : (string) $typeId;
        $safePage = max(1, $page);
        $safeSort = trim($sort) !== '' ? trim($sort) : 'date_desc';
        $safeEncapsulated = trim($encapsulated) !== '' ? trim($encapsulated) : 'all';

        return sprintf(
            'plugins:index:data:v%d:type:%s:page:%d:sort:%s:encapsulated:%s',
            self::currentVersion(),
            $typePart,
            $safePage,
            $safeSort,
            $safeEncapsulated
        );
    }
}
