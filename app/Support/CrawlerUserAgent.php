<?php

namespace App\Support;

/**
 * Best-effort "is this an automated client?" test based on the User-Agent header.
 *
 * Used to keep crawler traffic out of counters that are meant to measure people
 * (see ForumController::recordThreadView). It is deliberately biased towards
 * false positives: an occasional uncounted human view is cheaper than counting
 * every hit from the crawlers that make up a large share of this site's traffic.
 * Never use it as a security or access control gate — the header is attacker
 * controlled and trivially spoofed.
 */
class CrawlerUserAgent
{
    /**
     * Case-insensitive substrings that mark a client as a crawler, scraper, feed
     * reader, or bare HTTP library rather than a browser driven by a person.
     */
    private const TOKENS = [
        'crawler',
        'crawling',
        'spider',
        'slurp',
        'archiver',
        'scrapy',
        'feedfetcher',
        'facebookexternalhit',
        'meta-externalagent',
        'webindexer',
        'ahrefs',
        'semrush',
        'mj12',
        'yandex',
        'bingpreview',
        'headlesschrome',
        'phantomjs',
        'lighthouse',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'libwww-perl',
        'guzzlehttp',
        'node-fetch',
        'okhttp',
        'java/',
        'curl/',
        'wget/',
        'axios/',
    ];

    public static function matches(?string $userAgent): bool
    {
        $agent = mb_strtolower(trim((string) $userAgent));

        // No browser omits the header; scripted clients routinely do.
        if ($agent === '') {
            return true;
        }

        foreach (self::TOKENS as $token) {
            if (str_contains($agent, $token)) {
                return true;
            }
        }

        // Catches the large "…bot" family in one rule — Googlebot, bingbot, ClaudeBot,
        // Claude-SearchBot, GPTBot, PetalBot, DotBot and friends — without matching
        // "bot" mid-word. A few Android device names end in "bot" (Cubot); dropping
        // those handful of views is the accepted cost of not enumerating every crawler.
        return preg_match('/bot(?![a-z])/', $agent) === 1;
    }
}
