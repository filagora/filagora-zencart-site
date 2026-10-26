<?php

namespace App\Support;

use Filagora\Core\Content\MarkdownRenderer;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class FormattedContentRenderer
{
    /**
     * Cache key namespace. Bump the version when MarkdownRenderer's output
     * changes, so old HTML is not served from a warm cache after a deploy.
     */
    private const CACHE_PREFIX = 'formatted-content:v4:';

    /**
     * @param  CacheRepository|null  $cache  Omit to render without caching.
     */
    public function __construct(
        private MarkdownRenderer $markdownRenderer,
        private ?CacheRepository $cache = null,
        private int $cacheTtlSeconds = 86400,
    ) {}

    /**
     * Render post/message content to HTML, reusing a cached result when the
     * same source text has been rendered before.
     *
     * Rendering is expensive out of proportion to its output: a CommonMark
     * parse followed by a dozen regex passes that each loop until stable,
     * plus one attachment lookup per [attach] tag. A thread page renders
     * this once per post, and post bodies almost never change after they
     * are written, so the hit rate is high.
     */
    public function render(?string $content): string
    {
        $markdown = (string) ($content ?? '');

        if ($markdown === '') {
            return '';
        }

        if ($this->cache === null) {
            return $this->markdownRenderer->render($markdown);
        }

        return $this->cache->remember(
            self::CACHE_PREFIX.hash('xxh128', $markdown),
            max(60, $this->cacheTtlSeconds),
            fn (): string => $this->markdownRenderer->render($markdown),
        );
    }

    /**
     * Render without consulting or populating the cache.
     *
     * Live preview endpoints render text that changes on every keystroke and
     * is never read back, so caching it only churns the store.
     */
    public function renderUncached(?string $content): string
    {
        return $this->markdownRenderer->render($content);
    }

    public function plainText(?string $content): string
    {
        $html = $this->render($content);
        $withBlockSpacing = preg_replace(
            '/<\/?(?:blockquote|br|details|div|h[1-6]|hr|li|ol|p|pre|summary|ul)\b[^>]*>/i',
            ' ',
            $html,
        ) ?? $html;
        $decoded = html_entity_decode(strip_tags($withBlockSpacing), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;

        return trim($collapsed);
    }
}
