<?php

namespace App\Support;

class LegacyTextDecoder
{
    /**
     * Imported vBulletin titles/names were stored with literal HTML entities
     * (e.g. "Support Site Issues &amp; Questions") and escaped quotes/slashes
     * rather than the plain text they display as. Blade's `{{ }}` output
     * escaping then double-encodes that stored markup, so it must be
     * unescaped once here before display or an ampersand renders as the
     * literal text "&amp;" instead of "&".
     */
    public static function decode(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return html_entity_decode(stripslashes($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Same decoding, for legacy description/body fields that are rendered
     * as raw HTML rather than plain text. Strips any <script> tags that
     * decoding could otherwise reveal, plus the inline `style`/`class` and
     * `on*` handler attributes that imported vBulletin descriptions carry
     * (e.g. `style="font-size: x-large; white-space: nowrap;"` on the
     * security forum's blurb, which otherwise renders oversized and
     * unwrapped on the forums index).
     */
    public static function renderDescription(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $html = self::decode($value);
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? $html;

        return preg_replace(
            '/\s+(?:style|class|on\w+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s">]+)/i',
            '',
            $html
        ) ?? $html;
    }
}
