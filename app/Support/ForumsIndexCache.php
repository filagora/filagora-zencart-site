<?php

namespace App\Support;

use App\Services\ForumSearchVisibilityResolver;
use Illuminate\Support\Facades\Cache;

class ForumsIndexCache
{
    public const SCOPE_GUEST = 'guest';

    public const SCOPE_STAFF = 'staff';

    public const SCOPE_REGISTERED = 'registered';

    public const SCOPE_MEMBER = 'member';

    public static function keyForScope(string $scope): string
    {
        return "forums:index:meta:{$scope}:v1";
    }

    /**
     * @param  list<int>  $forumIds
     */
    public static function signatureForForumIds(array $forumIds): string
    {
        return sha1(implode(',', $forumIds));
    }

    public static function ttlSeconds(): int
    {
        return max(30, (int) config('forums.index_cache_ttl_seconds', 300));
    }

    public static function flushAll(): void
    {
        Cache::forget(self::keyForScope(self::SCOPE_GUEST));
        Cache::forget(self::keyForScope(self::SCOPE_STAFF));
        Cache::forget(self::keyForScope(self::SCOPE_REGISTERED));
        Cache::forget(self::keyForScope(self::SCOPE_MEMBER));
        // The forum search's forum directory rides on the same invalidation
        // points, including the query-builder stats updates that bypass
        // model events.
        ForumSearchVisibilityResolver::flushDirectory();
    }
}
