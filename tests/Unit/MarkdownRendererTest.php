<?php

namespace Tests\Unit;

use App\Markdown\BlankLineGapExtension;
use App\Markdown\ZenCartHtmlTransformer;
use Closure;
use Filagora\Core\Content\ExtensionRegistry;
use Filagora\Core\Content\MarkdownRenderer;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    public function test_it_preserves_unicode_emoji_as_visible_content(): void
    {
        $html = $this->renderer()->render('🙂 🙁 😉 😄 😛 😮');

        $this->assertStringContainsString('🙂 🙁 😉 😄 😛 😮', $html);
    }

    public function test_it_renders_markdown_and_application_extensions(): void
    {
        $renderer = $this->renderer(attachmentExists: fn (int $id): bool => $id === 123);

        $html = $renderer->render(<<<'MARKDOWN'
**Bold** and [a link](https://example.com)

[center][color=red]Centered[/color][/center]

[u]Underlined[/u] [sup]2[/sup] [sub]n[/sub] [highlight]Marked[/highlight] [size=18]Large[/size] [font=Verdana]Font[/font]

[spoiler=Answer]Hidden **detail**[/spoiler]

[video]https://example.com/video[/video]

[attach=CONFIG]123[/attach]
MARKDOWN);

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<a rel="nofollow noopener noreferrer" target="_blank" class="external-link" href="https://example.com">a link</a>', $html);
        $this->assertStringContainsString('<span class="block text-center"><span style="color:red;">Centered</span></span>', $html);
        $this->assertStringContainsString('<u>Underlined</u>', $html);
        $this->assertStringContainsString('<sup>2</sup>', $html);
        $this->assertStringContainsString('<sub>n</sub>', $html);
        $this->assertStringContainsString('<mark>Marked</mark>', $html);
        $this->assertStringContainsString('font-size:18px', $html);
        $this->assertStringContainsString('font-family:Verdana, sans-serif', $html);
        $this->assertStringContainsString('<details><summary>Answer</summary>Hidden <strong>detail</strong></details>', $html);
        $this->assertStringContainsString('href="https://example.com/video"', $html);
        $this->assertStringContainsString('attachmentid=123&amp;disposition=inline', $html);
    }

    public function test_it_escapes_raw_html_and_rejects_unsafe_links_and_extension_values(): void
    {
        $html = $this->renderer()->render(
            '<script>alert(1)</script> [bad](javascript:alert(2)) [color=red;position:fixed]Text[/color] [font=x;display:none]Visible[/font] [video]javascript:alert(3)[/video]'
        );

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('position:fixed', $html);
        $this->assertStringNotContainsString('display:none', $html);
        $this->assertStringContainsString('Text', $html);
        $this->assertStringContainsString('Visible', $html);
    }

    public function test_a_single_newline_renders_as_a_visible_line_break(): void
    {
        $html = $this->renderer()->render("First line\nSecond line");

        $this->assertStringContainsString("First line<br />\nSecond line", $html);
    }

    public function test_blank_line_paragraph_breaks_are_unaffected_by_soft_break_config(): void
    {
        $html = $this->renderer()->render("First paragraph\n\nSecond paragraph");

        $this->assertStringContainsString('<p>First paragraph</p>', $html);
        $this->assertStringContainsString('<p>Second paragraph</p>', $html);
        $this->assertStringNotContainsString('<br', $html);
    }

    public function test_a_single_blank_line_does_not_get_a_blank_gap_attribute(): void
    {
        $html = $this->renderer()->render("First paragraph\n\nSecond paragraph");

        $this->assertStringNotContainsString('data-blank-gap', $html);
    }

    public function test_two_blank_lines_between_paragraphs_get_a_larger_gap_attribute(): void
    {
        $html = $this->renderer()->render("First paragraph\n\n\nSecond paragraph");

        $this->assertStringContainsString('<p data-blank-gap="2">Second paragraph</p>', $html);
    }

    public function test_three_blank_lines_between_paragraphs_get_an_even_larger_gap_attribute(): void
    {
        $html = $this->renderer()->render("First paragraph\n\n\n\nSecond paragraph");

        $this->assertStringContainsString('<p data-blank-gap="3">Second paragraph</p>', $html);
    }

    public function test_excessive_blank_lines_are_capped(): void
    {
        $html = $this->renderer()->render('First paragraph'.str_repeat("\n", 11).'Second paragraph');

        $this->assertStringContainsString('<p data-blank-gap="4">Second paragraph</p>', $html);
    }

    public function test_blank_gap_attribute_is_not_applied_inside_a_blockquote(): void
    {
        $html = $this->renderer()->render("> First quoted paragraph\n>\n>\n> Second quoted paragraph");

        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<p>Second quoted paragraph</p>', $html);
        $this->assertStringNotContainsString('data-blank-gap', $html);
    }

    public function test_external_links_open_in_a_new_tab_with_an_indicator(): void
    {
        $html = $this->renderer()->render('[Zen Cart forums](https://www.zen-cart.com/threads/207282)');

        $this->assertStringContainsString(
            '<a rel="nofollow noopener noreferrer" target="_blank" class="external-link" href="https://www.zen-cart.com/threads/207282">Zen Cart forums</a>',
            $html,
        );
    }

    public function test_links_to_the_same_site_stay_in_the_current_tab(): void
    {
        $renderer = $this->renderer(siteHost: 'lara-forum.test');

        $html = $renderer->render('[a thread](https://lara-forum.test/threads/5) and [a relative link](/threads/5)');

        $this->assertStringContainsString('<a href="https://lara-forum.test/threads/5">a thread</a>', $html);
        $this->assertStringContainsString('<a href="/threads/5">a relative link</a>', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('external-link', $html);
    }

    public function test_links_to_a_different_host_stay_external_even_with_a_known_site_host(): void
    {
        $renderer = $this->renderer(siteHost: 'lara-forum.test');

        $html = $renderer->render('[docs](https://example.com/docs)');

        $this->assertStringContainsString(
            '<a rel="nofollow noopener noreferrer" target="_blank" class="external-link" href="https://example.com/docs">docs</a>',
            $html,
        );
    }

    public function test_mailto_links_are_not_treated_as_external(): void
    {
        $html = $this->renderer()->render('[email me](mailto:someone@example.com)');

        $this->assertStringContainsString('<a href="mailto:someone@example.com">email me</a>', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
    }

    public function test_github_issue_shorthand_is_auto_linked_to_the_core_issue_tracker(): void
    {
        $html = $this->renderer()->render('This corrects a bug; see GitHub issue #13 for details.');

        $this->assertStringContainsString(
            '<a rel="nofollow noopener noreferrer" target="_blank" class="external-link" href="https://github.com/zencart/zencart/issues/13">GitHub issue #13</a>',
            $html,
        );
    }

    public function test_github_issue_shorthand_matches_case_insensitively_without_the_hash(): void
    {
        $html = $this->renderer()->render('github issue 42 was closed.');

        $this->assertStringContainsString('href="https://github.com/zencart/zencart/issues/42"', $html);
        $this->assertStringContainsString('>github issue 42</a>', $html);
    }

    public function test_github_issue_shorthand_is_not_double_linked_inside_an_existing_link(): void
    {
        $html = $this->renderer()->render('[See GitHub issue #13 here](https://example.com/notes)');

        $this->assertStringContainsString(
            '<a rel="nofollow noopener noreferrer" target="_blank" class="external-link" href="https://example.com/notes">See GitHub issue #13 here</a>',
            $html,
        );
        $this->assertStringNotContainsString('issues/13', $html);
    }

    public function test_github_issue_shorthand_is_left_alone_inside_code(): void
    {
        $html = $this->renderer()->render('`GitHub issue #13`');

        $this->assertStringContainsString('<code>GitHub issue #13</code>', $html);
        $this->assertStringNotContainsString('issues/13', $html);
    }

    public function test_a_mention_of_an_existing_user_links_to_their_member_page(): void
    {
        $renderer = $this->renderer(mentionedUserIds: fn (array $usernames): array => $usernames === ['wilt'] ? ['wilt' => 42] : []);

        $html = $renderer->render('Thanks @wilt for the fix.');

        $this->assertStringContainsString('<a href="/members/42">@wilt</a>', $html);
    }

    public function test_a_mention_matches_the_resolver_case_insensitively(): void
    {
        $renderer = $this->renderer(mentionedUserIds: fn (array $usernames): array => $usernames === ['wilt'] ? ['wilt' => 42] : []);

        $html = $renderer->render('Thanks @Wilt for the fix.');

        $this->assertStringContainsString('<a href="/members/42">@Wilt</a>', $html);
    }

    public function test_a_mention_with_no_matching_user_is_left_as_plain_text(): void
    {
        $renderer = $this->renderer(mentionedUserIds: fn (array $usernames): array => []);

        $html = $renderer->render('Thanks @nobody for the fix.');

        $this->assertStringContainsString('@nobody', $html);
        $this->assertStringNotContainsString('<a href', $html);
    }

    public function test_a_mention_trailing_sentence_punctuation_is_not_linked(): void
    {
        $renderer = $this->renderer(mentionedUserIds: fn (array $usernames): array => $usernames === ['wilt'] ? ['wilt' => 42] : []);

        $html = $renderer->render('Thanks @wilt.');

        $this->assertStringContainsString('<a href="/members/42">@wilt</a>.', $html);
    }

    public function test_a_mention_inside_code_is_left_alone(): void
    {
        $renderer = $this->renderer(mentionedUserIds: fn (array $usernames): array => ['wilt' => 42]);

        $html = $renderer->render('`@wilt`');

        $this->assertStringContainsString('<code>@wilt</code>', $html);
        $this->assertStringNotContainsString('<a href', $html);
    }

    public function test_a_mention_already_inside_a_link_is_not_double_linked(): void
    {
        $renderer = $this->renderer(mentionedUserIds: fn (array $usernames): array => ['wilt' => 42]);

        $html = $renderer->render('[Reach out to @wilt here](https://example.com/contact)');

        $this->assertStringContainsString('>Reach out to @wilt here</a>', $html);
        $this->assertStringNotContainsString('<a href="/members/42">', $html);
    }

    public function test_superscript_subscript_and_highlight_extensions_render_nested_markdown_safely(): void
    {
        $html = $this->renderer()->render(
            '[sup]2 **bold**[/sup] [sub]n[/sub] [highlight]<script>alert(1)</script>[/highlight]'
        );

        $this->assertStringContainsString('<sup>2 <strong>bold</strong></sup>', $html);
        $this->assertStringContainsString('<sub>n</sub>', $html);
        $this->assertStringContainsString('<mark>&lt;script&gt;alert(1)&lt;/script&gt;</mark>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * The site's renderer as the app builds it: the core renderer with the site's extension and
     * transformer registered.
     */
    private function renderer(?Closure $attachmentExists = null, ?string $siteHost = null, ?Closure $mentionedUserIds = null): MarkdownRenderer
    {
        config()->set('filagora.markdown.internal_hosts', $siteHost !== null ? [$siteHost] : []);

        $registry = new ExtensionRegistry;
        $registry->extension(new BlankLineGapExtension);
        $registry->transformer(new ZenCartHtmlTransformer($attachmentExists, $mentionedUserIds));

        return new MarkdownRenderer($registry);
    }
}
