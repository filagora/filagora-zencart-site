<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Renders staff-authored CMS prose. Unlike MarkdownRenderer (untrusted forum posts),
 * inline HTML in existing content keeps working, but the result is passed through an
 * allowlist sanitizer so scripts, event handlers and unsafe URLs never reach the page.
 */
final class CmsMarkdown
{
    private static ?MarkdownConverter $converter = null;

    private static ?HtmlSanitizer $sanitizer = null;

    public static function block(?string $markdown): HtmlString
    {
        return new HtmlString(self::renderHtml($markdown));
    }

    /**
     * Renders without a wrapping paragraph, for text that sits inside a list item, span or sentence.
     */
    public static function inline(?string $markdown): HtmlString
    {
        $html = self::renderHtml($markdown);

        if (preg_match('#^<p>(.*)</p>$#s', $html, $matches) === 1 && ! str_contains($matches[1], '</p>')) {
            $html = $matches[1];
        }

        return new HtmlString($html);
    }

    public static function renderHtml(?string $markdown): string
    {
        $markdown = trim((string) ($markdown ?? ''));
        if ($markdown === '') {
            return '';
        }

        $html = self::converter()->convert($markdown)->getContent();

        return trim(self::sanitizer()->sanitize($html));
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 50,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);

            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer === null) {
            $config = (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowAttribute('class', ['a', 'span', 'i', 'strong', 'em', 'code', 'p', 'ul', 'ol', 'li', 'div'])
                ->allowAttribute('target', 'a')
                ->allowAttribute('rel', 'a')
                ->forceAttribute('a', 'rel', 'noopener noreferrer');

            self::$sanitizer = new HtmlSanitizer($config);
        }

        return self::$sanitizer;
    }
}
