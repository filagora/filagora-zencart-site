<?php

namespace App\Support;

use Illuminate\Support\Str;

class MetaDescription
{
    public const DEFAULT_LIMIT = 160;

    /**
     * From text that may contain markup or entities (plugin and forum
     * descriptions): strip tags, decode entities, collapse whitespace, shorten.
     */
    public static function fromMarkup(?string $text, int $limit = self::DEFAULT_LIMIT): string
    {
        $plain = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5);

        return self::fromPlainText($plain, $limit);
    }

    /**
     * From text that is already plain (an extracted post excerpt): collapse
     * whitespace and shorten. Tags are not stripped, so a literal "a < b" in a
     * post survives.
     */
    public static function fromPlainText(?string $text, int $limit = self::DEFAULT_LIMIT): string
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return Str::limit($collapsed, $limit, '...');
    }
}
