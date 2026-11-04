<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Puts text into a Markdown mail as text. Blade's `{{ }}` escapes HTML but not Markdown, so a thread
 * title or a post excerpt could otherwise add its own links and images to mail sent from the site.
 * Markdown's characters become HTML entities: Markdown reads those as plain characters, and the
 * plain-text version of the mail decodes them, so neither shows any trace.
 */
class MailText
{
    public static function plain(mixed $text): HtmlString
    {
        return new HtmlString((string) preg_replace_callback(
            '/&(?:#\d+|[a-z]+);|[\\\\`*_{}\[\]()#+\-.!|~:]/i',
            static fn (array $match): string => strlen($match[0]) > 1 ? $match[0] : '&#'.ord($match[0]).';',
            e((string) $text),
        ));
    }
}
