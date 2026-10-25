<?php

namespace App\Markdown;

use App\Models\User;
use Closure;
use Filagora\Core\Contracts\HtmlTransformer;
use Filagora\Forums\Support\Models;
use Illuminate\Support\Facades\DB;

/**
 * What the site adds to rendered post HTML beyond CommonMark: the old forum's tags that live in
 * years of stored posts ([u], [sup], [sub], [highlight], [left], [center], [right], [indent],
 * [color], [size], [font], [spoiler], [video], [attach]), "GitHub issue #13" links to the core
 * tracker, and @mention links to member pages. The input is already safe HTML (raw HTML is
 * escaped before this runs) and every step keeps it safe: colours, sizes and fonts are checked
 * against short allow-lists, and only http(s) URLs are linked.
 *
 * Links to other sites are marked by the markdown renderer itself; the GitHub issue anchors made
 * here are marked the same way (new tab, no follow, the external-link class).
 */
final class ZenCartHtmlTransformer implements HtmlTransformer
{
    /**
     * Target for the "GitHub issue #13" auto-link. Plugin release notes routinely cite core issues
     * by number without a link (GitHub only autolinks that shorthand on github.com itself), so this
     * always resolves against the core repo rather than a plugin's own.
     */
    private const GITHUB_ISSUE_TRACKER_URL = 'https://github.com/zencart/zencart/issues/';

    /** @var array<string,string> */
    private const FONT_FAMILIES = [
        'arial' => 'Arial, sans-serif',
        'arial black' => '"Arial Black", sans-serif',
        'comic sans ms' => '"Comic Sans MS", cursive',
        'courier new' => '"Courier New", monospace',
        'georgia' => 'Georgia, serif',
        'impact' => 'Impact, sans-serif',
        'lucida console' => '"Lucida Console", monospace',
        'tahoma' => 'Tahoma, sans-serif',
        'times new roman' => '"Times New Roman", serif',
        'trebuchet ms' => '"Trebuchet MS", sans-serif',
        'verdana' => 'Verdana, sans-serif',
    ];

    /**
     * @param  Closure(int): bool|null  $attachmentExistsResolver  Whether an old attachment id exists; the table when null.
     * @param  Closure(list<string>): array<string,int>|null  $mentionedUserIdsResolver  User ids by lowercased username; the table when null.
     */
    public function __construct(
        private readonly ?Closure $attachmentExistsResolver = null,
        private readonly ?Closure $mentionedUserIdsResolver = null,
    ) {}

    public function transform(string $html): string
    {
        $html = $this->linkGitHubIssueReferences($html);
        $html = $this->linkMentions($html);
        $html = $this->replacePairedTag($html, 'u', static fn (string $body): string => '<u>'.$body.'</u>');
        $html = $this->replacePairedTag($html, 'sup', static fn (string $body): string => '<sup>'.$body.'</sup>');
        $html = $this->replacePairedTag($html, 'sub', static fn (string $body): string => '<sub>'.$body.'</sub>');
        $html = $this->replacePairedTag($html, 'highlight', static fn (string $body): string => '<mark>'.$body.'</mark>');

        foreach (['left', 'center', 'right'] as $alignment) {
            $html = $this->replacePairedTag(
                $html,
                $alignment,
                static fn (string $body): string => '<span class="block text-'.$alignment.'">'.$body.'</span>',
            );
        }

        $html = $this->replacePairedTag($html, 'indent', static fn (string $body): string => '<blockquote>'.$body.'</blockquote>');
        $html = $this->replaceAttributedTag($html, 'color', function (string $attribute, string $body): string {
            $color = $this->normalizeAttribute($attribute);

            return $this->isSafeColor($color) ? '<span style="color:'.$color.';">'.$body.'</span>' : $body;
        });
        $html = $this->replaceAttributedTag($html, 'size', function (string $attribute, string $body): string {
            $size = $this->normalizeAttribute($attribute);

            if (! preg_match('/^\d{1,2}$/', $size) || (int) $size < 8 || (int) $size > 72) {
                return $body;
            }

            return '<span style="font-size:'.(int) $size.'px;">'.$body.'</span>';
        });
        $html = $this->replaceAttributedTag($html, 'font', function (string $attribute, string $body): string {
            $font = self::FONT_FAMILIES[strtolower($this->normalizeAttribute($attribute))] ?? null;

            return $font === null ? $body : '<span style="font-family:'.$font.';">'.$body.'</span>';
        });
        $html = $this->replaceSpoilers($html);
        $html = $this->replaceVideos($html);

        return $this->replaceAttachments($html);
    }

    /** @param Closure(string): string $renderer */
    private function replacePairedTag(string $html, string $tag, Closure $renderer): string
    {
        $pattern = '/\['.preg_quote($tag, '/').'\](.*?)\[\/'.preg_quote($tag, '/').'\]/is';

        return $this->replaceUntilStable($html, $pattern, static fn (array $matches): string => $renderer((string) $matches[1]));
    }

    /** @param Closure(string,string): string $renderer */
    private function replaceAttributedTag(string $html, string $tag, Closure $renderer): string
    {
        $pattern = '/\['.preg_quote($tag, '/').'=([^\]]+)\](.*?)\[\/'.preg_quote($tag, '/').'\]/is';

        return $this->replaceUntilStable($html, $pattern, static fn (array $matches): string => $renderer((string) $matches[1], (string) $matches[2]));
    }

    /** @param Closure(array<int,string>): string $renderer */
    private function replaceUntilStable(string $html, string $pattern, Closure $renderer): string
    {
        do {
            $previous = $html;
            $html = preg_replace_callback($pattern, $renderer, $html) ?? $html;
        } while ($html !== $previous);

        return $html;
    }

    private function replaceSpoilers(string $html): string
    {
        return $this->replaceUntilStable(
            $html,
            '/\[spoiler(?:=([^\]]+))?\](.*?)\[\/spoiler\]/is',
            function (array $matches): string {
                $summary = $this->normalizeAttribute((string) ($matches[1] ?? ''));
                $safeSummary = htmlspecialchars($summary !== '' ? $summary : 'Spoiler', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return '<details><summary>'.$safeSummary.'</summary>'.(string) $matches[2].'</details>';
            },
        );
    }

    private function replaceVideos(string $html): string
    {
        return $this->replaceUntilStable($html, '/\[video\](.*?)\[\/video\]/is', function (array $matches): string {
            $label = trim(strip_tags(html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $url = $this->safeUrl($label);
            if ($url === null) {
                return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a href="'.$safeUrl.'" target="_blank" rel="noopener noreferrer">'.$safeUrl.'</a>';
        });
    }

    private function replaceAttachments(string $html): string
    {
        return $this->replaceUntilStable($html, '/\[attach(?:=([^\]]+))?\](.*?)\[\/attach\]/is', function (array $matches): string {
            $mode = strtoupper($this->normalizeAttribute((string) ($matches[1] ?? '')));
            $rawId = trim(strip_tags((string) $matches[2]));
            if (! preg_match('/^\d+$/', $rawId) || (int) $rawId <= 0 || ! $this->attachmentExists((int) $rawId)) {
                return '[Attachment no longer available]';
            }

            $attachmentId = (int) $rawId;
            $url = '/attachment.php?attachmentid='.$attachmentId;
            if ($mode === 'CONFIG') {
                return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer"><img src="'.$url.'&amp;disposition=inline" alt="Attachment '.$attachmentId.'" loading="lazy"></a>';
            }

            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">Attachment #'.$attachmentId.'</a>';
        });
    }

    private function attachmentExists(int $legacyAttachmentId): bool
    {
        if ($this->attachmentExistsResolver !== null) {
            return ($this->attachmentExistsResolver)($legacyAttachmentId);
        }

        return Models::class('post_attachment')::query()->where('legacy_attachment_id', $legacyAttachmentId)->exists();
    }

    /**
     * Auto-links a bare "GitHub issue #13" (or "github issue 13", any
     * casing/spacing of the same shorthand) mention to the core issue
     * tracker. Skips text already inside a link, code span, or code block,
     * both to avoid double-linking and to leave literal/quoted text alone.
     * Runs before markExternalLinks() so the anchors it creates pick up the
     * same new-tab-with-icon treatment as any other external link.
     */
    private function linkGitHubIssueReferences(string $html): string
    {
        $protectedBlocks = [];
        $protect = function (string $match) use (&$protectedBlocks): string {
            $key = "\0gh-issue-protected-".count($protectedBlocks)."\0";
            $protectedBlocks[$key] = $match;

            return $key;
        };

        $protectedHtml = preg_replace_callback(
            '/<a\b[^>]*>.*?<\/a>|<code\b[^>]*>.*?<\/code>|<pre\b[^>]*>.*?<\/pre>/is',
            static fn (array $matches): string => $protect((string) $matches[0]),
            $html,
        ) ?? $html;

        $linkedHtml = preg_replace_callback(
            '/\bgithub issue\s*#?\s*(\d+)\b/i',
            static function (array $matches): string {
                $url = self::GITHUB_ISSUE_TRACKER_URL.(int) $matches[1];

                return '<a rel="nofollow noopener noreferrer" target="_blank" class="external-link" href="'.$url.'">'.$matches[0].'</a>';
            },
            $protectedHtml,
        ) ?? $protectedHtml;

        return strtr($linkedHtml, $protectedBlocks);
    }

    /**
     * Links a `@username` mention to that member's profile page, using the
     * same username shape and existence check PostMentionService uses to
     * decide who gets notified - an unrecognised name (typo, or just prose
     * with an "@" in it) is left as plain text rather than a dead link.
     * Skips text already inside a link, code span, or code block, same as
     * linkGitHubIssueReferences() above.
     */
    private function linkMentions(string $html): string
    {
        $protectedBlocks = [];
        $protect = function (string $match) use (&$protectedBlocks): string {
            $key = "\0mention-protected-".count($protectedBlocks)."\0";
            $protectedBlocks[$key] = $match;

            return $key;
        };

        $protectedHtml = preg_replace_callback(
            '/<a\b[^>]*>.*?<\/a>|<code\b[^>]*>.*?<\/code>|<pre\b[^>]*>.*?<\/pre>/is',
            static fn (array $matches): string => $protect((string) $matches[0]),
            $html,
        ) ?? $html;

        if (preg_match_all('/(?<![\w@])@([A-Za-z0-9._-]{2,30})/u', $protectedHtml, $matches) === 0) {
            return strtr($protectedHtml, $protectedBlocks);
        }

        $usernames = [];
        foreach ($matches[1] as $rawName) {
            $name = mb_strtolower(rtrim((string) $rawName, '.-'));
            if (mb_strlen($name) >= 2) {
                $usernames[$name] = true;
            }
        }

        $userIdsByUsername = $this->resolveMentionedUserIds(array_keys($usernames));

        if ($userIdsByUsername === []) {
            return strtr($protectedHtml, $protectedBlocks);
        }

        $linkedHtml = preg_replace_callback(
            '/(?<![\w@])@([A-Za-z0-9._-]{2,30})/u',
            static function (array $matches) use ($userIdsByUsername): string {
                $raw = (string) $matches[1];
                $name = rtrim($raw, '.-');
                $trailing = mb_substr($raw, mb_strlen($name));
                $userId = $userIdsByUsername[mb_strtolower($name)] ?? null;

                if ($userId === null) {
                    return $matches[0];
                }

                return '<a href="/members/'.$userId.'">@'.$name.'</a>'.$trailing;
            },
            $protectedHtml,
        ) ?? $protectedHtml;

        return strtr($linkedHtml, $protectedBlocks);
    }

    /**
     * @param  list<string>  $lowercasedUsernames
     * @return array<string,int> user id keyed by lowercased username
     */
    private function resolveMentionedUserIds(array $lowercasedUsernames): array
    {
        if ($lowercasedUsernames === []) {
            return [];
        }

        if ($this->mentionedUserIdsResolver !== null) {
            return ($this->mentionedUserIdsResolver)($lowercasedUsernames);
        }

        $userIdsByUsername = [];

        foreach (User::query()->whereIn(DB::raw('LOWER(username)'), $lowercasedUsernames)->get(['id', 'username']) as $user) {
            $userIdsByUsername[mb_strtolower((string) $user->username)] = (int) $user->id;
        }

        return $userIdsByUsername;
    }

    private function normalizeAttribute(string $attribute): string
    {
        return trim(html_entity_decode($attribute, ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B'\"");
    }

    private function isSafeColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color)
            || (bool) preg_match('/^[a-zA-Z]{3,20}$/', $color);
    }

    private function safeUrl(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }
}
