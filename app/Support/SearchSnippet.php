<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A short excerpt of plain text around the first match of a search query,
 * with every match wrapped in <mark>. Each piece of text is escaped on its
 * own, so post content can never inject markup and a match never splits an
 * HTML entity.
 */
final class SearchSnippet
{
    public static function highlight(string $text, string $query, int $radius = 90): HtmlString
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $query = trim($query);

        if ($text === '') {
            return new HtmlString('');
        }

        $position = $query !== '' ? mb_stripos($text, $query) : false;
        $start = $position === false ? 0 : max(0, $position - $radius);
        $excerpt = mb_substr($text, $start, $radius * 2 + mb_strlen($query));

        $prefix = $start > 0 ? '… ' : '';
        $suffix = $start + mb_strlen($excerpt) < mb_strlen($text) ? ' …' : '';

        // Split the raw text around the matches and escape each piece on its
        // own; marking after escaping could land inside an entity such as
        // &amp; when someone searches for "amp".
        $pieces = $query === ''
            ? [$excerpt]
            : (preg_split('/('.preg_quote($query, '/').')/iu', $excerpt, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$excerpt]);
        $escaped = '';
        foreach ($pieces as $index => $piece) {
            // With a capture group, odd positions are the matches.
            $escaped .= $index % 2 === 1
                ? '<mark class="bg-[#fdf1c7] text-inherit">'.e($piece).'</mark>'
                : e($piece);
        }

        return new HtmlString($prefix.$escaped.$suffix);
    }
}
