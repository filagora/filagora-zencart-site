<?php

namespace App\Services;

use App\Filament\RichEditor\Plugins\InsertTrackedLinkPlugin;
use App\Models\Newsletter;
use App\Models\NewsletterBlock;
use App\NewsletterBlockType;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Filagora\Cms\Models\CmsMediaAsset;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NewsletterContentRenderer
{
    public function __construct(
        private readonly NewsletterLinkTracker $linkTracker,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderHtml(Newsletter $newsletter, array $context = []): string
    {
        return $this->htmlFromBlocks(
            is_array($newsletter->content_blocks) ? $newsletter->content_blocks : [],
            $this->context($newsletter, $context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderText(Newsletter $newsletter, array $context = []): string
    {
        return $this->textFromBlocks(
            is_array($newsletter->content_blocks) ? $newsletter->content_blocks : [],
            $this->context($newsletter, $context),
        );
    }

    /**
     * Shapes campaign content blocks for storage. Every campaign is guaranteed a header
     * reference at the start and a footer reference at the end, but reusable block
     * references are kept intact (not resolved) so library edits stay live for unsent
     * campaigns.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function preparedBlocks(array $blocks): array
    {
        $content = collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block) && trim((string) ($block['type'] ?? '')) !== '')
            ->map(fn (array $block): array => $this->withBlockKey($block))
            ->reject(fn (array $block): bool => $this->referencesSystemChrome($block))
            ->values()
            ->all();

        return [
            $this->systemBlockReference(NewsletterBlockType::Header),
            ...$content,
            $this->systemBlockReference(NewsletterBlockType::Footer),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $context
     */
    public function htmlFromBlocks(array $blocks, array $context = []): string
    {
        return (string) view('emails.newsletter.content-blocks', [
            'blocks' => $this->resolveForRender($this->preparedBlocks($blocks), $context),
            'context' => $context,
        ])->render();
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $context
     */
    public function textFromBlocks(array $blocks, array $context = []): string
    {
        return trim(collect($this->resolveForRender($this->preparedBlocks($blocks), $context))
            ->map(fn (array $block): string => $this->textForBlock($block, $context))
            ->filter()
            ->implode("\n\n"));
    }

    /**
     * Resolves the current live destination and label for a specific content-block
     * instance, used by the click-tracking redirect (never wrapped in a tracked URL).
     *
     * @return array{url: string, label: string, newsletter_block_id: int|null}|null
     */
    public function resolveBlockLink(Newsletter $newsletter, string $blockKey): ?array
    {
        $prepared = $this->preparedBlocks(is_array($newsletter->content_blocks) ? $newsletter->content_blocks : []);
        $resolved = $this->resolveForRender($prepared, []);

        $block = collect($resolved)->first(fn (array $block): bool => ($block['block_key'] ?? null) === $blockKey);

        if ($block !== null && trim((string) ($block['button_url'] ?? '')) !== '') {
            return [
                'url' => $block['button_url'],
                'label' => $block['button_label'],
                'newsletter_block_id' => $block['newsletter_block_id'] ?? null,
            ];
        }

        foreach ($resolved as $candidate) {
            if (($candidate['type'] ?? null) !== 'text') {
                continue;
            }

            $link = $this->findTrackedLinkInHtml((string) ($candidate['body_html'] ?? ''), $blockKey);

            if ($link !== null) {
                return [
                    'url' => $link['url'],
                    'label' => $link['label'],
                    'newsletter_block_id' => $candidate['newsletter_block_id'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Strips the auto-managed header/footer references from a stored `content_blocks`
     * array so they never appear as editable rows in the campaign form — they're
     * re-injected unconditionally by `preparedBlocks()` at save time.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function stripSystemChrome(array $blocks): array
    {
        return collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block) && trim((string) ($block['type'] ?? '')) !== '')
            ->reject(fn (array $block): bool => $this->referencesSystemChrome($block))
            ->values()
            ->all();
    }

    private function withBlockKey(array $block): array
    {
        $block['block_key'] = trim((string) ($block['block_key'] ?? '')) ?: (string) Str::uuid();

        return $block;
    }

    private function referencesSystemChrome(array $block): bool
    {
        $type = trim((string) ($block['type'] ?? ''));

        if (in_array($type, ['shared_header', 'shared_footer'], true)) {
            return true;
        }

        if ($type !== 'reusable') {
            return false;
        }

        return $this->findLibraryBlock(Arr::get($block, 'newsletter_block_id'))?->type->isSystemManaged() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    private function systemBlockReference(NewsletterBlockType $type): array
    {
        $libraryBlock = NewsletterBlock::query()
            ->where('type', $type->value)
            ->where('is_system', true)
            ->first();

        return $this->withBlockKey([
            'type' => 'reusable',
            'newsletter_block_id' => $libraryBlock?->id,
        ]);
    }

    /**
     * Resolves reusable block references (and legacy inline shared_header/shared_footer
     * blocks) into concrete renderable content, live at render time.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function resolveForRender(array $blocks, array $context): array
    {
        return collect($blocks)
            ->map(fn (array $block): ?array => $this->resolveBlock($block, $context))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function resolveBlock(array $block, array $context): ?array
    {
        $type = trim((string) ($block['type'] ?? ''));
        $blockKey = trim((string) ($block['block_key'] ?? '')) ?: null;

        if ($type === 'shared_header') {
            return $this->normalizeBlock(['type' => 'header'], $blockKey, $context);
        }

        if ($type === 'shared_footer') {
            return $this->normalizeBlock(['type' => 'footer'], $blockKey, $context);
        }

        if ($type === 'reusable') {
            $libraryBlock = $this->findLibraryBlock(Arr::get($block, 'newsletter_block_id'));

            if (! $libraryBlock instanceof NewsletterBlock || ! $libraryBlock->is_active) {
                return null;
            }

            $content = is_array($libraryBlock->content) ? $libraryBlock->content : [];

            return $this->normalizeBlock([...$content, 'type' => $libraryBlock->type->value], $blockKey, $context, $libraryBlock->id);
        }

        return $this->normalizeBlock($block, $blockKey, $context);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeBlock(array $block, ?string $blockKey, array $context, ?int $newsletterBlockId = null): array
    {
        $assetId = Arr::get($block, 'asset_id');
        $asset = is_numeric($assetId) ? CmsMediaAsset::query()->find((int) $assetId) : null;
        $buttonUrl = trim((string) ($block['button_url'] ?? ''));
        $buttonLabel = trim((string) ($block['button_label'] ?? ''));

        return [
            'type' => trim((string) ($block['type'] ?? '')),
            'block_key' => $blockKey,
            'newsletter_block_id' => $newsletterBlockId,
            'heading' => trim((string) ($block['heading'] ?? '')),
            'body_html' => $this->trackTaggedLinksInHtml(trim((string) ($block['body_html'] ?? '')), $context),
            'asset_id' => is_numeric($assetId) ? (int) $assetId : null,
            'asset_url' => $asset?->publicUrl(),
            'asset_alt' => trim((string) ($block['asset_alt'] ?? $asset?->alt_text ?? '')),
            'caption' => trim((string) ($block['caption'] ?? '')),
            'button_label' => $buttonLabel,
            'button_url' => $this->trackedButtonUrl($buttonUrl, $blockKey, $context),
        ];
    }

    /**
     * Wraps a block's link in a first-party tracked redirect when rendering for a
     * persisted newsletter (real send or preview). Left untouched when there is no
     * newsletter in context (e.g. the draft-save body_html/body_text cache) or when
     * resolving the live destination for the click redirect itself.
     *
     * @param  array<string, mixed>  $context
     */
    private function trackedButtonUrl(string $url, ?string $blockKey, array $context): string
    {
        if ($url === '' || $blockKey === null) {
            return $url;
        }

        $newsletter = $context['newsletter'] ?? null;

        if (! $newsletter instanceof Newsletter || ! $newsletter->exists) {
            return $url;
        }

        $newsletterSendId = $context['newsletter_send_id'] ?? null;

        return $this->linkTracker->trackedUrl(
            $newsletter,
            $blockKey,
            is_numeric($newsletterSendId) ? (int) $newsletterSendId : null,
        );
    }

    /**
     * Rewrites every link inserted via `InsertTrackedLinkPlugin` inside a text block's rich
     * content into a first-party tracked redirect, using the unique id embedded in the
     * link's marker class as the tracking key. Mirrors `trackedButtonUrl()`'s guard: left
     * untouched when there is no persisted newsletter in context, so the marker class stays
     * intact for `findTrackedLinkInHtml()` to resolve the live (untracked) destination.
     *
     * @param  array<string, mixed>  $context
     */
    private function trackTaggedLinksInHtml(string $html, array $context): string
    {
        if ($html === '' || ! str_contains($html, InsertTrackedLinkPlugin::ID_MARKER_PREFIX)) {
            return $html;
        }

        $newsletter = $context['newsletter'] ?? null;

        if (! $newsletter instanceof Newsletter || ! $newsletter->exists) {
            return $html;
        }

        $newsletterSendId = $context['newsletter_send_id'] ?? null;
        $newsletterSendId = is_numeric($newsletterSendId) ? (int) $newsletterSendId : null;

        return $this->withTrackedLinkAnchors($html, function (DOMElement $anchor, string $linkId, bool $isButton) use ($newsletter, $newsletterSendId): void {
            $anchor->setAttribute('href', $this->linkTracker->trackedUrl($newsletter, $linkId, $newsletterSendId));
            $anchor->removeAttribute('class');
            $anchor->setAttribute('style', $isButton
                ? 'display:inline-block;padding:12px 20px;background:#8a4b19;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;'
                : 'color:#8a4b19;font-weight:600;text-decoration:underline;');
        });
    }

    /**
     * Resolves the current live destination/label for a specific tracked rich-text link,
     * used by the click redirect (never wrapped in a tracked URL itself). Returns null when
     * the given id isn't found in this block's content.
     *
     * @return array{url: string, label: string}|null
     */
    private function findTrackedLinkInHtml(string $html, string $linkId): ?array
    {
        if ($html === '' || ! str_contains($html, InsertTrackedLinkPlugin::ID_MARKER_PREFIX.$linkId)) {
            return null;
        }

        $found = null;

        $this->withTrackedLinkAnchors($html, function (DOMElement $anchor, string $candidateId) use ($linkId, &$found): void {
            if ($candidateId !== $linkId || $found !== null) {
                return;
            }

            $href = trim($anchor->getAttribute('href'));

            if ($href === '') {
                return;
            }

            $found = [
                'url' => $href,
                'label' => trim($anchor->textContent),
            ];
        });

        return $found;
    }

    /**
     * Parses an HTML fragment, invokes the callback for every anchor carrying an
     * `InsertTrackedLinkPlugin` marker class, and returns the (possibly mutated) HTML.
     *
     * @param  callable(DOMElement, string, bool): void  $callback
     */
    private function withTrackedLinkAnchors(string $html, callable $callback): string
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[contains(@class, "'.InsertTrackedLinkPlugin::ID_MARKER_PREFIX.'")]');

        foreach ($anchors ?: [] as $anchor) {
            /** @var DOMElement $anchor */
            $class = (string) $anchor->getAttribute('class');

            if (! preg_match('/'.preg_quote(InsertTrackedLinkPlugin::ID_MARKER_PREFIX, '/').'([0-9a-f-]{36})/', $class, $idMatch)) {
                continue;
            }

            $isButton = str_contains($class, InsertTrackedLinkPlugin::STYLE_MARKER_PREFIX.'button');

            $callback($anchor, $idMatch[1], $isButton);
        }

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        $result = '';

        foreach ($wrapper?->childNodes ?? [] as $child) {
            $result .= (string) $dom->saveHTML($child);
        }

        return $result;
    }

    private function findLibraryBlock(mixed $id): ?NewsletterBlock
    {
        return is_numeric($id) ? NewsletterBlock::query()->find((int) $id) : null;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     */
    private function textForBlock(array $block, array $context): string
    {
        return match ($block['type']) {
            'header' => $this->joinParts([
                trim((string) data_get($context, 'branding.header_eyebrow', '')),
                trim((string) data_get($context, 'newsletter.subject', '')),
            ]),
            'text' => $this->joinParts([
                $block['heading'],
                trim(strip_tags((string) $block['body_html'])),
            ]),
            'image' => $this->joinParts([
                $block['caption'],
                $block['asset_alt'],
                $block['asset_url'],
            ]),
            'button' => $this->joinParts([
                $block['button_label'],
                $block['button_url'],
            ]),
            'footer' => $this->joinParts([
                trim((string) data_get($context, 'branding.footer_text', '')),
                $this->joinParts([
                    trim((string) data_get($context, 'branding.manage_link_label', '')),
                    trim((string) data_get($context, 'unsubscribe_url', '')),
                ]),
            ]),
            default => '',
        };
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function joinParts(array $parts): string
    {
        return trim(collect($parts)
            ->map(static fn (string $part): string => trim($part))
            ->filter()
            ->implode("\n"));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function context(Newsletter $newsletter, array $context): array
    {
        return array_merge([
            'newsletter' => $newsletter,
            'branding' => [],
            'unsubscribe_url' => '',
        ], $context);
    }
}
