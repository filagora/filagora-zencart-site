<?php

namespace App\Support;

class ThreadUrlParser
{
    /**
     * Extract a thread id from a pasted URL, recognising both the current
     * `/threads/{id}` route and the legacy vBulletin `showthread.php?t=`
     * query format that imported post bodies and old bookmarks still use.
     * Returns null for anything that isn't a link to this site or doesn't
     * resolve to a thread id, without checking whether that thread exists
     * or is visible to any particular viewer - callers apply their own
     * eligibility rules on top of the id this returns.
     */
    public static function extractThreadId(string $url): ?int
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! is_string($appHost) || strcasecmp($parts['host'], $appHost) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $queryParams);

        $threadId = null;
        if (preg_match('#/threads/(\d+)#', $path, $matches) === 1) {
            $threadId = (int) $matches[1];
        } elseif (str_contains($path, 'showthread.php') && isset($queryParams['t'])) {
            $threadId = (int) $queryParams['t'];
        }

        return $threadId !== null && $threadId > 0 ? $threadId : null;
    }
}
