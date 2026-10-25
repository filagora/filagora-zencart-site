<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Repairs Windows-1252 percent-escapes in legacy vBulletin URL paths.
 *
 * vBulletin built its title slugs by percent-encoding the RAW Windows-1252
 * bytes of the thread title, so an apostrophe in a title reached the wire as
 * a lone `%92`:
 *
 *   /showthread.php/98511-HELP!-Canada-Post-module-doesn%92t-work-in-FRENCH
 *
 * A lone byte in 0x80-0xFF is never valid UTF-8. Laravel's global
 * ValidatePathEncoding rejects the whole request with `400 Malformed URL.`
 * before routing, so every such thread is permanently unreachable from a
 * bookmark, an old forum link, or a search result. 363 imported threads have
 * a title that produces one of these slugs (measured August 29, 2026).
 *
 * This class REPLACES ValidatePathEncoding rather than running beside it --
 * see bootstrap/app.php. Replacement is deliberate: it makes the repair
 * ordering-proof. A separate middleware would have to be positioned ahead of
 * the framework's, and nothing in the global stack enforces that order.
 *
 * Only the legacy `*.php` endpoints are repaired. Everything else still gets
 * the framework's 400, because nothing else on this site has a legitimate
 * reason to carry a non-UTF-8 path.
 */
class RepairLegacyPathEncoding extends ValidatePathEncoding
{
    /**
     * Every legacy `*.php` script this APPLICATION registers -- the
     * routes/legacy.php set plus attachment.php and plugin_check.php from
     * routes/web.php -- at the site root or under /forum.
     *
     * Deliberately NOT the same list as the "Legacy XYZ.PHP file support"
     * alternation in /etc/nginx/sites-available/zcforum-vhost, and it does not
     * need to be. nginx forwards a narrower set: it answers external.php with
     * 410 and newreply.php with 404 before PHP-FPM is reached, so those two
     * entries are inert behind the current vhost and kept only so that this
     * class stays correct if that ever changes. Conversely plugin_check.php is
     * forwarded by nginx and is registered here with a `{legacyPath?}`
     * parameter, so it must be present.
     */
    private const LEGACY_SCRIPT_PATH = '#^/(?:forum/)?(?:attachment|blog|content|downloads|entry|external|faq|forum|forumdisplay|member|misc|newreply|plugin_check|printthread|private|search|sendmessage|showcase|showpost|showthread)\.php(?:/|$)#i';

    /**
     * Matches one well-formed UTF-8 sequence per alternative, falling through
     * to a single captured byte for anything that cannot begin one. Group 1
     * therefore participates only for bytes that need repairing.
     */
    private const UTF8_SEQUENCE = '/[\x00-\x7F]+'
        .'|[\xC2-\xDF][\x80-\xBF]'
        .'|\xE0[\xA0-\xBF][\x80-\xBF]'
        .'|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
        .'|\xED[\x80-\x9F][\x80-\xBF]'
        .'|\xF0[\x90-\xBF][\x80-\xBF]{2}'
        .'|[\xF1-\xF3][\x80-\xBF]{3}'
        .'|\xF4[\x80-\x8F][\x80-\xBF]{2}'
        .'|(.)/s';

    public function handle(Request $request, Closure $next): Response
    {
        $requestUri = (string) $request->server->get('REQUEST_URI', '');
        [$path, $query] = array_pad(explode('?', $requestUri, 2), 2, null);

        if ($this->needsRepair((string) $path)) {
            $this->rewritePath($request, $this->repair((string) $path), $query);
        }

        // Anything still malformed -- a non-legacy path, or an escape that is
        // not recoverable as Windows-1252 -- keeps the framework's 400.
        return parent::handle($request, $next);
    }

    private function needsRepair(string $path): bool
    {
        if ($path === '' || mb_check_encoding(rawurldecode($path), 'UTF-8')) {
            return false;
        }

        // Matched against the still-encoded path so that a `%2F` inside a slug
        // cannot forge an extra segment and pass this check.
        return preg_match(self::LEGACY_SCRIPT_PATH, $path) === 1;
    }

    /**
     * Rewrites ONLY the escapes that cannot begin a UTF-8 sequence, treating
     * each as Windows-1252, and leaves every other byte of the path exactly as
     * it arrived.
     *
     * Decoding the whole path and re-encoding it segment-by-segment would be
     * shorter, but it silently promotes an encoded `%2F` to a real path
     * separator -- turning one slug into several segments, and manufacturing
     * the very `.php`-after-`.php` shape the vhost exists to reject. Byte-wise
     * rewriting is what keeps `%2F` encoded, `%25` single-encoded, and `!`
     * literal.
     */
    private function repair(string $path): string
    {
        // One token per byte: a `%XX` escape, or a single literal byte. Token
        // index therefore lines up with byte offset in the decoded string,
        // which is what lets one bad byte be replaced in isolation.
        preg_match_all('/%[0-9A-Fa-f]{2}|./s', $path, $matches);
        $tokens = $matches[0];

        $decoded = '';
        foreach ($tokens as $token) {
            $decoded .= str_starts_with($token, '%') && strlen($token) === 3
                ? chr((int) hexdec(substr($token, 1)))
                : $token;
        }

        if (preg_match_all(self::UTF8_SEQUENCE, $decoded, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return $path;
        }

        foreach ($found as $match) {
            // Group 1 participates only for a byte that cannot begin a valid
            // UTF-8 sequence; its offset is the index of the token to rewrite.
            if (! isset($match[1]) || $match[1][1] < 0) {
                continue;
            }

            $tokens[$match[1][1]] = rawurlencode(
                (string) mb_convert_encoding($match[1][0], 'UTF-8', 'Windows-1252'),
            );
        }

        return implode('', $tokens);
    }

    /**
     * Rewrites REQUEST_URI on the request that is already bound in the
     * container, rather than passing a duplicate down the pipeline: the
     * `request` instance is resolved by name all over the application, and a
     * duplicate would leave those callers reading the unrepaired URL.
     *
     * initialize() is what clears Symfony's cached requestUri/pathInfo/baseUrl,
     * so the rewrite is invisible without it. The QUERY_STRING is passed
     * through byte-for-byte -- `showthread.php?229558-slug` carries its id
     * there with no `=`, and re-parsing that form loses the id.
     */
    private function rewritePath(Request $request, string $path, ?string $query): void
    {
        $request->server->set('REQUEST_URI', $path.($query === null ? '' : '?'.$query));

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
        );
    }
}
