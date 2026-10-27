<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use App\Models\NewsletterBlock;
use App\Models\NewsletterLinkClick;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use App\NewsletterSendStatus;
use App\NewsletterStatus;
use App\NewsletterSubscriberStatus;
use App\Services\NewsletterContentRenderer;
use App\Services\NewsletterLinkTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterLinkClickControllerTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_valid_signed_click_redirects_to_the_destination_and_records_a_click(): void
    {
        $newsletter = $this->newsletterWithButtonBlock('https://example.test/spring-sale', 'Shop now');
        $blockKey = $newsletter->content_blocks[1]['block_key'];

        $url = app(NewsletterLinkTracker::class)->trackedUrl($newsletter, $blockKey, null);

        $response = $this->get($url);

        $response->assertRedirect('https://example.test/spring-sale');

        $click = NewsletterLinkClick::query()->firstOrFail();
        $this->assertTrue($click->newsletter->is($newsletter));
        $this->assertNull($click->newsletter_send_id);
        $this->assertSame($blockKey, $click->block_key);
        $this->assertSame('Shop now', $click->label);
        $this->assertSame('https://example.test/spring-sale', $click->destination_url);
        $this->assertNotNull($click->clicked_at);
        $this->assertNotNull($click->ip_hash);
        $this->assertNotNull($click->newsletter_block_id);
        $this->assertTrue($click->libraryBlock->exists);
    }

    public function test_click_attributes_to_the_recipients_send_when_present(): void
    {
        $newsletter = $this->newsletterWithButtonBlock('https://example.test/recipient-offer', 'Claim offer');
        $blockKey = $newsletter->content_blocks[1]['block_key'];

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'recipient@example.test',
            'normalized_email' => 'recipient@example.test',
            'name' => 'Recipient',
            'status' => NewsletterSubscriberStatus::Subscribed,
            'source' => 'public_signup',
            'subscribed_at' => now(),
            'confirmed_at' => now(),
        ]);
        $send = NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Sent,
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        $url = app(NewsletterLinkTracker::class)->trackedUrl($newsletter, $blockKey, $send->id);

        $this->get($url)->assertRedirect('https://example.test/recipient-offer');

        $click = NewsletterLinkClick::query()->firstOrFail();
        $this->assertTrue($click->send->is($send));
    }

    public function test_rendered_campaign_html_uses_a_tracked_url_that_resolves_when_clicked(): void
    {
        $newsletter = $this->newsletterWithButtonBlock('https://example.test/from-email', 'Read more');

        $html = app(NewsletterContentRenderer::class)->renderHtml($newsletter);

        $this->assertMatchesRegularExpression('#href="(https?://[^"]*/newsletter/click/[^"]+)"#', $html);
        preg_match('#href="(https?://[^"]*/newsletter/click/[^"]+)"#', $html, $matches);
        $trackedUrl = html_entity_decode($matches[1]);

        $this->get($trackedUrl)->assertRedirect('https://example.test/from-email');
    }

    public function test_unsigned_click_url_is_rejected(): void
    {
        $newsletter = $this->newsletterWithButtonBlock('https://example.test/unsigned', 'Unsigned');
        $blockKey = $newsletter->content_blocks[1]['block_key'];

        $this->get(route('newsletter.link-click', ['newsletter' => $newsletter, 'blockKey' => $blockKey]))
            ->assertForbidden();

        $this->assertSame(0, NewsletterLinkClick::query()->count());
    }

    public function test_tampered_signed_click_url_is_rejected(): void
    {
        $newsletter = $this->newsletterWithButtonBlock('https://example.test/tampered', 'Tampered');
        $blockKey = $newsletter->content_blocks[1]['block_key'];

        $url = app(NewsletterLinkTracker::class)->trackedUrl($newsletter, $blockKey, null);
        $tampered = str_replace('Tampered', 'Hacked', $url).'&tamper=1';

        $this->get($tampered)->assertForbidden();
        $this->assertSame(0, NewsletterLinkClick::query()->count());
    }

    public function test_clicks_on_the_same_library_block_aggregate_across_different_newsletters(): void
    {
        $block = NewsletterBlock::query()->create([
            'key' => 'evergreen-ad',
            'name' => 'Evergreen ad',
            'type' => 'button',
            'content' => [
                'button_label' => 'See details',
                'button_url' => 'https://example.test/evergreen',
            ],
        ]);

        $renderer = app(NewsletterContentRenderer::class);

        $newsletterA = Newsletter::query()->create([
            'title' => 'Campaign A',
            'subject' => 'Campaign A',
            'body_html' => '',
            'content_blocks' => $renderer->preparedBlocks([
                ['type' => 'reusable', 'newsletter_block_id' => $block->id],
            ]),
            'status' => NewsletterStatus::Draft,
        ]);
        $newsletterB = Newsletter::query()->create([
            'title' => 'Campaign B',
            'subject' => 'Campaign B',
            'body_html' => '',
            'content_blocks' => $renderer->preparedBlocks([
                ['type' => 'reusable', 'newsletter_block_id' => $block->id],
            ]),
            'status' => NewsletterStatus::Draft,
        ]);

        $tracker = app(NewsletterLinkTracker::class);
        $this->get($tracker->trackedUrl($newsletterA, $newsletterA->content_blocks[1]['block_key'], null));
        $this->get($tracker->trackedUrl($newsletterB, $newsletterB->content_blocks[1]['block_key'], null));

        $this->assertSame(2, NewsletterLinkClick::query()->where('newsletter_block_id', $block->id)->count());
    }

    public function test_click_for_an_unknown_block_key_returns_not_found(): void
    {
        $newsletter = $this->newsletterWithButtonBlock('https://example.test/known', 'Known');

        $url = app(NewsletterLinkTracker::class)->trackedUrl($newsletter, 'not-a-real-block-key', null);

        $this->get($url)->assertNotFound();
        $this->assertSame(0, NewsletterLinkClick::query()->count());
    }

    public function test_tracked_link_embedded_in_rich_text_redirects_and_records_a_click(): void
    {
        [$newsletter, $linkId] = $this->newsletterWithTrackedRichTextLink('https://example.test/rich-text-cta', 'Read the full story');

        $url = app(NewsletterLinkTracker::class)->trackedUrl($newsletter, $linkId, null);

        $response = $this->get($url);

        $response->assertRedirect('https://example.test/rich-text-cta');

        $click = NewsletterLinkClick::query()->firstOrFail();
        $this->assertSame($linkId, $click->block_key);
        $this->assertSame('Read the full story', $click->label);
        $this->assertSame('https://example.test/rich-text-cta', $click->destination_url);
        $this->assertNull($click->newsletter_block_id);
    }

    public function test_rendered_campaign_html_rewrites_tracked_rich_text_links_with_button_styling(): void
    {
        [$newsletter] = $this->newsletterWithTrackedRichTextLink('https://example.test/styled-cta', 'Shop now', 'button');

        $html = app(NewsletterContentRenderer::class)->renderHtml($newsletter);

        $this->assertStringNotContainsString('https://example.test/styled-cta', $html);
        $this->assertStringContainsString('/newsletter/click/', $html);
        $this->assertStringContainsString('background:#8a4b19', $html);
    }

    public function test_rendered_campaign_html_rewrites_tracked_rich_text_links_with_plain_link_styling(): void
    {
        [$newsletter] = $this->newsletterWithTrackedRichTextLink('https://example.test/plain-cta', 'Learn more', 'link');

        $html = app(NewsletterContentRenderer::class)->renderHtml($newsletter);

        $this->assertStringContainsString('text-decoration:underline', $html);
        $this->assertStringNotContainsString('background:#8a4b19', $html);
    }

    public function test_editing_a_reusable_text_block_tracked_link_aggregates_by_library_block(): void
    {
        $linkId = (string) Str::uuid();
        $block = NewsletterBlock::query()->create([
            'key' => 'evergreen-text-block',
            'name' => 'Evergreen text block',
            'type' => 'text',
            'content' => [
                'heading' => 'Evergreen',
                'body_html' => '<p>Copy. <a class="nl-tracked-link nl-tracked-link--button nl-tracked-link-id--'.$linkId.'" href="https://example.test/evergreen-text">Go</a></p>',
            ],
        ]);

        $renderer = app(NewsletterContentRenderer::class);
        $newsletter = Newsletter::query()->create([
            'title' => 'Reusable text block test',
            'subject' => 'Reusable text block test',
            'body_html' => '',
            'content_blocks' => $renderer->preparedBlocks([
                ['type' => 'reusable', 'newsletter_block_id' => $block->id],
            ]),
            'status' => NewsletterStatus::Draft,
        ]);

        $this->get(app(NewsletterLinkTracker::class)->trackedUrl($newsletter, $linkId, null))
            ->assertRedirect('https://example.test/evergreen-text');

        $click = NewsletterLinkClick::query()->firstOrFail();
        $this->assertTrue($click->libraryBlock->is($block));
    }

    /**
     * @return array{0: Newsletter, 1: string}
     */
    private function newsletterWithTrackedRichTextLink(string $url, string $label, string $style = 'button'): array
    {
        $linkId = (string) Str::uuid();
        $class = "nl-tracked-link nl-tracked-link--{$style} nl-tracked-link-id--{$linkId}";

        $renderer = app(NewsletterContentRenderer::class);
        $preparedBlocks = $renderer->preparedBlocks([
            [
                'type' => 'text',
                'heading' => 'Update',
                'body_html' => "<p>Some intro copy. <a class=\"{$class}\" href=\"{$url}\">{$label}</a></p>",
            ],
        ]);

        $newsletter = Newsletter::query()->create([
            'title' => 'Rich text tracked link test',
            'subject' => 'Rich text tracked link test',
            'body_html' => '',
            'content_blocks' => $preparedBlocks,
            'status' => NewsletterStatus::Draft,
        ]);

        return [$newsletter, $linkId];
    }

    private function newsletterWithButtonBlock(string $buttonUrl, string $buttonLabel): Newsletter
    {
        $block = NewsletterBlock::query()->create([
            'key' => 'click-test-'.str()->random(8),
            'name' => 'Click test block',
            'type' => 'button',
            'content' => [
                'button_label' => $buttonLabel,
                'button_url' => $buttonUrl,
            ],
        ]);

        $renderer = app(NewsletterContentRenderer::class);
        $preparedBlocks = $renderer->preparedBlocks([
            ['type' => 'reusable', 'newsletter_block_id' => $block->id],
        ]);

        return Newsletter::query()->create([
            'title' => 'Click tracking test',
            'subject' => 'Click tracking test',
            'body_html' => $renderer->htmlFromBlocks($preparedBlocks),
            'body_text' => $renderer->textFromBlocks($preparedBlocks),
            'content_blocks' => $preparedBlocks,
            'status' => NewsletterStatus::Draft,
        ]);
    }
}
