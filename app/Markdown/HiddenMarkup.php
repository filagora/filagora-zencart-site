<?php

namespace App\Markdown;

/**
 * Takes markup out of rendered HTML while text is rewritten, leaving a placeholder for each piece,
 * so that a pattern can neither match inside it nor write into it. The HTML must not already hold
 * a null byte, which is what marks a placeholder.
 */
final class HiddenMarkup
{
    /**
     * One tag. The markdown renderer and the transformer escape `<` and `>` everywhere else,
     * attribute values included.
     */
    public const TAG = '/<[^<>]*>/';

    /**
     * Placeholders hold a `#` so that none passes a check on a colour, size, font or id.
     */
    private const PLACEHOLDER = '/\x00#\d+#\x00/';

    /** @var array<string,string> */
    private array $hidden = [];

    /**
     * @return string|null null when the pattern could not be run, so the HTML must not be rewritten
     */
    public function hide(string $html, string $pattern = self::TAG): ?string
    {
        return preg_replace_callback($pattern, function (array $matches): string {
            $placeholder = "\0#".count($this->hidden)."#\0";
            $this->hidden[$placeholder] = (string) $matches[0];

            return $placeholder;
        }, $html);
    }

    public function restore(string $text): string
    {
        return strtr($text, $this->hidden);
    }

    /**
     * The text without its placeholders, for a value taken out of it to be checked or escaped.
     */
    public static function remove(string $text): string
    {
        return (string) preg_replace(self::PLACEHOLDER, '', $text);
    }
}
