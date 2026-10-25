<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;

/**
 * Tags each top-level block with how many blank lines separated it from the previous block in the
 * source, so CSS can scale the rendered gap instead of CommonMark's default of normalising any run
 * of blank lines into one uniform paragraph break. Scoped to the document's direct children only:
 * content nested inside blockquotes or list items keeps its tight or loose list spacing.
 */
final class BlankLineGapExtension implements ExtensionInterface
{
    /**
     * Blank-line counts beyond this are all treated as this value, so a post pasted with dozens of
     * blank lines cannot blow out the layout with an unbounded gap.
     */
    private const MAX_BLANK_LINE_GAP = 4;

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, $this->tagGaps(...));
    }

    private function tagGaps(DocumentParsedEvent $event): void
    {
        $previous = null;

        foreach ($event->getDocument()->children() as $block) {
            if (! $block instanceof AbstractBlock) {
                continue;
            }

            if ($previous instanceof AbstractBlock && $previous->getEndLine() !== null && $block->getStartLine() !== null) {
                $blankLines = $block->getStartLine() - $previous->getEndLine() - 1;

                if ($blankLines > 1) {
                    $attributes = $block->data->get('attributes', []);
                    $attributes['data-blank-gap'] = (string) min($blankLines, self::MAX_BLANK_LINE_GAP);
                    $block->data->set('attributes', $attributes);
                }
            }

            $previous = $block;
        }
    }
}
