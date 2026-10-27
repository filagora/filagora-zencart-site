<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsBlock;
use Filagora\Cms\Models\CmsNavigation;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Models\CmsTag;
use Filagora\Core\Models\Setting;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class CmsPageControllerTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_published_page_is_rendered_on_frontend_route(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'about-us',
            'title' => 'About Us',
            'excerpt' => 'Published page excerpt',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $page->blocks()->create([
            'type' => 'feature_list',
            'name' => 'Intro',
            'sort_order' => 10,
            'data' => ['content' => 'Hello world'],
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('About Us')
            ->assertSee('Published page excerpt');
    }

    public function test_published_page_renders_article_structured_data(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'structured-data-page',
            'title' => 'Structured Data Page',
            'excerpt' => 'Fallback summary for structured data.',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
            'seo_title' => 'Structured Data SEO Title',
            'seo_description' => 'Structured data SEO description.',
        ]);

        $page->blocks()->create([
            'type' => 'feature_list',
            'name' => 'Intro',
            'sort_order' => 10,
            'data' => ['content' => 'Structured content'],
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('<script type="application/ld+json">', false)
            ->assertSee('"@type": "Article"', false)
            ->assertSee('"headline": "Structured Data SEO Title"', false)
            ->assertSee('"description": "Structured data SEO description."', false)
            ->assertSee('"url": "'.route('cms.pages.show', $page->slug).'"', false);
    }

    public function test_cms_structured_data_carries_dates_and_the_site_as_publisher(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'dated-structured-page',
            'title' => 'Dated Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subDays(3)->startOfSecond(),
        ]);

        $html = $this->get(route('cms.pages.show', $page->slug))->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $data = json_decode($matches[1], true);

        $this->assertSame($page->published_at->toIso8601String(), $data['datePublished']);
        $this->assertSame($page->fresh()->updated_at->toIso8601String(), $data['dateModified']);
        $this->assertSame('Organization', $data['publisher']['@type']);
        $this->assertSame(route('home'), $data['publisher']['url']);
        $this->assertSame(asset('assets/images/logo/zen-cart-logo.png'), $data['publisher']['logo']['url']);
        $this->assertArrayNotHasKey('author', $data);
    }

    public function test_structured_data_is_a_web_page_for_pages_with_their_own_template_or_route(): void
    {
        $donate = CmsPage::query()->where('slug', 'donate')->firstOrFail();

        $this->get(route('donate'))
            ->assertOk()
            ->assertSee('"@type": "WebPage"', false)
            ->assertDontSee('"@type": "Article"', false);

        $custom = CmsPage::query()->create([
            'slug' => 'custom-route-page',
            'custom_route' => '/custom-route-page',
            'title' => 'Custom Route Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/custom-route-page')
            ->assertOk()
            ->assertSee('"@type": "WebPage"', false)
            ->assertSee('"url": "'.url('/custom-route-page').'"', false);

        $this->assertNotSame($donate->id, $custom->id);
    }

    public function test_structured_data_cannot_break_out_of_its_script_tag(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'script-breakout-page',
            'title' => 'Breakout Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
            'seo_title' => 'Tom & Jerry </script><script>alert(1)</script>',
            'seo_description' => 'Description with <b>markup</b> & an ampersand.',
        ]);

        $html = $this->get(route('cms.pages.show', $page->slug))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<script type="application/ld+json">'));
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $decoded = json_decode($matches[1] ?? '', true);

        $this->assertIsArray($decoded);
        $this->assertSame('Tom & Jerry </script><script>alert(1)</script>', $decoded['headline']);
        $this->assertSame('Description with <b>markup</b> & an ampersand.', $decoded['description']);
    }

    public function test_pages_that_are_not_cms_pages_do_not_emit_structured_data(): void
    {
        $this->get(route('forums.index'))
            ->assertOk()
            ->assertDontSee('application/ld+json', false);

        $this->get(route('plugins.index'))
            ->assertOk()
            ->assertDontSee('application/ld+json', false);
    }

    public function test_raw_html_block_renders_unescaped_html(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'raw-html-page',
            'title' => 'Raw Html Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $page->blocks()->create([
            'type' => 'raw_html',
            'name' => 'Trusted Snippet',
            'sort_order' => 5,
            'data' => ['html' => '<div class="snippet"><strong>Raw HTML</strong> snippet</div>'],
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('cms-page-frame', false)
            ->assertSee('cms-rich-content', false)
            ->assertSee('<strong>Raw HTML</strong>', false);
    }

    public function test_draft_page_returns_404_without_preview_token_and_renders_with_valid_preview_token(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'preview-only',
            'title' => 'Preview Only',
            'status' => CmsPageStatus::DRAFT,
            'preview_token' => 'preview-token-123',
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertNotFound();

        $this->get(route('cms.pages.show', ['slug' => $page->slug, 'preview' => 'preview-token-123']))
            ->assertOk()
            ->assertSee('Preview Only');

        $this->get(route('cms.pages.show', ['slug' => $page->slug, 'preview' => 'wrong-token']))
            ->assertNotFound();
    }

    public function test_comparison_matrix_block_renders_structured_table_content(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'comparison-matrix-page',
            'title' => 'Comparison Matrix Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $page->blocks()->create([
            'type' => 'comparison_matrix',
            'name' => 'Compare Hero',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'The Strategic Choice',
                'title' => 'How We Compare',
                'body' => 'Quick breakdown copy.',
                'headers' => [
                    ['label' => 'Feature', 'emphasize' => false],
                    ['label' => 'Zen Cart', 'emphasize' => true],
                    ['label' => 'WooCommerce', 'emphasize' => false],
                ],
                'rows' => [
                    [
                        'feature' => 'Monthly License Fee',
                        'values' => ['$0', '$39+'],
                        'highlight_column' => 1,
                    ],
                ],
            ],
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('The Strategic Choice')
            ->assertSee('How We Compare')
            ->assertSee('Quick breakdown copy.')
            ->assertSee('Monthly License Fee')
            ->assertSee('$39+');
    }

    public function test_stat_strip_block_renders_structured_metrics(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'stat-strip-page',
            'title' => 'Stat Strip Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $page->blocks()->create([
            'type' => 'stat_strip',
            'name' => 'Trust Metrics',
            'sort_order' => 10,
            'data' => [
                'title' => 'Why Choose Zen Cart',
                'items' => [
                    [
                        'headline' => '20+ Years On The Market',
                        'icon_class' => 'fa fa-graduation-cap',
                        'url' => '/about',
                        'emphasize' => false,
                    ],
                    [
                        'headline' => '50 000+ Active Shops',
                        'icon_class' => 'fa fa-store',
                        'url' => '/about',
                        'emphasize' => true,
                    ],
                ],
            ],
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Why Choose Zen Cart')
            ->assertSee('20+ Years On The Market')
            ->assertSee('50 000+ Active Shops')
            ->assertSee('/about', false);
    }

    public function test_tag_listing_only_includes_published_pages(): void
    {
        $tag = CmsTag::query()->create([
            'name' => 'Physics',
            'slug' => 'physics',
        ]);

        $published = CmsPage::query()->create([
            'slug' => 'published-physics',
            'title' => 'Published Physics',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subHour(),
        ]);
        $published->tags()->sync([$tag->id]);

        $draft = CmsPage::query()->create([
            'slug' => 'draft-physics',
            'title' => 'Draft Physics',
            'status' => CmsPageStatus::DRAFT,
        ]);
        $draft->tags()->sync([$tag->id]);

        $this->get(route('cms.pages.tag', $tag->slug))
            ->assertOk()
            ->assertSee('Published Physics')
            ->assertDontSee('Draft Physics');
    }

    public function test_api_pages_and_page_routes_enforce_published_or_preview_rules(): void
    {
        Carbon::setTestNow(now());

        $published = CmsPage::query()->create([
            'slug' => 'api-published',
            'title' => 'API Published',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $future = CmsPage::query()->create([
            'slug' => 'api-future',
            'title' => 'API Future',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->addDay(),
            'preview_token' => 'future-preview',
        ]);

        $draft = CmsPage::query()->create([
            'slug' => 'api-draft',
            'title' => 'API Draft',
            'status' => CmsPageStatus::DRAFT,
            'preview_token' => 'draft-preview',
        ]);

        $pagesResponse = $this->getJson('/api/cms/pages');
        $pagesResponse->assertOk();
        $slugs = collect($pagesResponse->json('data'))->pluck('slug')->all();
        $this->assertContains($published->slug, $slugs);
        $this->assertNotContains($future->slug, $slugs);
        $this->assertNotContains($draft->slug, $slugs);
        foreach ($pagesResponse->json('data') as $pagePayload) {
            $this->assertArrayNotHasKey('preview_token', $pagePayload);
        }

        $this->getJson('/api/cms/pages/'.$published->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $published->slug)
            ->assertJsonMissingPath('data.preview_token');

        $this->getJson('/api/cms/pages/'.$draft->slug)
            ->assertNotFound();

        $this->getJson('/api/cms/pages/'.$draft->slug.'?preview=draft-preview')
            ->assertOk()
            ->assertJsonPath('data.slug', $draft->slug)
            ->assertJsonMissingPath('data.preview_token');
    }

    public function test_api_navigation_returns_payload_and_404_for_unknown_navigation(): void
    {
        CmsNavigation::query()->create([
            'name' => 'header-contract-test',
            'items' => [
                ['label' => 'Forums', 'url' => '/forums', 'target' => '_self'],
            ],
        ]);

        $this->getJson('/api/cms/navigation/header-contract-test')
            ->assertOk()
            ->assertJsonPath('data.name', 'header-contract-test');

        $this->getJson('/api/cms/navigation/does-not-exist')
            ->assertNotFound();
    }

    public function test_api_block_returns_only_published_reusable_block(): void
    {
        CmsBlock::query()->create([
            'cms_page_id' => null,
            'slug' => 'hero-banner',
            'type' => 'raw_html',
            'name' => 'Hero Banner',
            'sort_order' => 10,
            'is_reusable' => true,
            'is_published' => true,
            'data' => ['html' => '<div>Hero</div>'],
        ]);

        CmsBlock::query()->create([
            'cms_page_id' => null,
            'slug' => 'hidden-banner',
            'type' => 'raw_html',
            'name' => 'Hidden Banner',
            'sort_order' => 20,
            'is_reusable' => true,
            'is_published' => false,
            'data' => ['html' => '<div>Hidden</div>'],
        ]);

        $this->getJson('/api/cms/blocks/hero-banner')
            ->assertOk()
            ->assertJsonPath('data.slug', 'hero-banner')
            ->assertJsonPath('data.name', 'Hero Banner')
            ->assertJsonPath('data.data.html', '<div>Hero</div>');

        $this->getJson('/api/cms/blocks/hidden-banner')
            ->assertNotFound();
    }

    public function test_bearer_token_without_api_cms_entitlement_is_forbidden_for_cms_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cms-api', ['cms:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/cms/pages')
            ->assertForbidden()
            ->assertJson([
                'message' => __('api.auth.token_entitlement_required'),
            ]);
    }

    public function test_entitled_bearer_token_can_read_cms_api(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'cms-token-page',
            'title' => 'CMS Token Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);
        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.cms.read',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
        $token = $user->createToken('cms-api', ['cms:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/cms/pages/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $page->slug);
    }

    public function test_privacy_route_renders_cms_page_using_privacy_template(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'privacy'],
            [
                'custom_route' => '/privacy',
                'title' => 'Privacy From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed Privacy Title',
                'excerpt' => 'Privacy summary copy.',
            ]
        );

        $page->blocks()->delete();

        $page->blocks()->create([
            'type' => 'raw_html',
            'name' => 'Policy',
            'sort_order' => 10,
            'data' => ['html' => '<p>CMS privacy block text</p>'],
        ]);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertViewIs('cms.templates.privacy')
            ->assertSee('<title>Managed Privacy Title</title>', false)
            ->assertSee('Privacy From CMS')
            ->assertSee('Privacy summary copy.')
            ->assertSee('Policy')
            ->assertSee('CMS privacy block text');
    }

    public function test_download_route_renders_cms_page_using_download_template(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'download'],
            [
                'custom_route' => '/download',
                'title' => 'Download From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed Download Title',
            ]
        );

        $page->blocks()->create([
            'type' => 'promo_hero',
            'name' => 'Download Hero',
            'sort_order' => 10,
            'data' => [
                'title' => 'CMS Download Heading',
                'body' => 'Managed from CMS blocks.',
                'benefits' => [
                    ['text' => 'Block-managed benefit'],
                ],
                'cta_label' => 'Download Zen Cart',
                'cta_url' => 'https://example.com/download.zip',
                'badge' => '9.9.9',
            ],
        ]);

        $this->get(route('download'))
            ->assertOk()
            ->assertViewIs('cms.templates.download')
            ->assertSee('<title>Managed Download Title</title>', false)
            ->assertSee('CMS Download Heading')
            ->assertSee('Managed from CMS blocks.')
            ->assertSee('Block-managed benefit')
            ->assertSee('9.9.9');
    }

    public function test_download_route_requires_a_cms_page_and_does_not_fall_back_to_legacy_blade_view(): void
    {
        CmsPage::query()->where('slug', 'download')->delete();

        $this->get(route('download'))
            ->assertNotFound();
    }

    public function test_home_route_renders_cms_page_using_home_template(): void
    {
        CmsPage::query()->updateOrCreate(
            ['slug' => 'home'],
            [
                'custom_route' => '/',
                'title' => 'Home From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed Home Title',
            ]
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('cms.templates.home')
            ->assertSee('<title>Managed Home Title</title>', false)
            ->assertSee('Commerce software with room to grow.')
            ->assertSee('Open Source. Merchant Owned. Community Powered.')
            ->assertSee('Get Started Now');
    }

    public function test_home_route_renders_webpage_structured_data(): void
    {
        CmsPage::query()->updateOrCreate(
            ['slug' => 'home'],
            [
                'custom_route' => '/',
                'title' => 'Home From CMS',
                'excerpt' => 'Home fallback summary',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed Home Title',
                'seo_description' => 'Managed home description',
            ]
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<script type="application/ld+json">', false)
            ->assertSee('"@type": "WebPage"', false)
            ->assertSee('"headline": "Managed Home Title"', false)
            ->assertSee('"description": "Managed home description"', false)
            ->assertSee('"url": "'.route('home').'"', false);
    }

    public function test_home_route_renders_cms_blocks_when_present(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'home'],
            [
                'custom_route' => '/',
                'title' => 'Home From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
            ]
        );

        $page->blocks()->create([
            'type' => 'home_hero',
            'name' => 'Home Hero',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'Managed Homepage',
                'title' => 'CMS Home Hero',
                'body' => 'Managed home body.',
                'stats' => [],
                'primary_label' => '',
                'primary_url' => '',
                'secondary_label' => '',
                'secondary_url' => '',
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('cms.templates.home')
            ->assertSee('CMS Home Hero')
            ->assertSee('Managed home body.')
            ->assertDontSee('FREE Open Source');
    }

    public function test_home_route_renders_home_specific_cms_blocks_when_present(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'home'],
            [
                'custom_route' => '/',
                'title' => 'Home From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
            ]
        );

        $page->blocks()->delete();

        $page->blocks()->create([
            'type' => 'home_hero',
            'name' => 'Homepage Hero',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'Open Source. Merchant Owned. Community Powered.',
                'title' => 'Commerce software with room to grow.',
                'body' => 'Build a store you control, extend it with plugins, and lean on a long-running support community without getting trapped in platform fees.',
                'stats' => [
                    ['label' => 'Track Record', 'value' => '20+ years'],
                    ['label' => 'Extensions', 'value' => '2,000+ plugins'],
                    ['label' => 'Ownership', 'value' => 'No platform tax'],
                ],
                'primary_label' => 'Get Started Now',
                'primary_url' => '/download',
                'secondary_label' => 'Learn More',
                'secondary_url' => '/about',
            ],
        ]);

        $page->blocks()->create([
            'type' => 'home_deploy_steps',
            'name' => 'Deploy Steps',
            'sort_order' => 20,
            'data' => [
                'eyebrow' => 'How It Works',
                'title' => 'Deploy your website in as little as 10 minutes',
                'steps' => [
                    [
                        'title' => '01. Download Zen Cart',
                        'body' => 'Download the zip file from our official repository',
                        'image_key' => 'cms-media/steps/step_1.jpg',
                        'image_position' => 'center 35%',
                        'open' => true,
                    ],
                ],
            ],
        ]);

        $page->blocks()->create([
            'type' => 'home_compare_section',
            'name' => 'Home Compare',
            'sort_order' => 30,
            'data' => [
                'eyebrow' => 'The Open Source Choice',
                'title' => 'Ownership vs. Subscriptions',
                'body' => 'In a market saturated with "rented" storefronts, Zen Cart® stands as a bastion of true digital ownership. Don\'t let your platform dictate your profit.',
                'ownership_title' => 'Why Zen Cart® Over Commercial Platforms?',
                'ownership_paragraphs' => [
                    [
                        'text' => 'There are dozens of open-source shopping cart programs available, but none come close to the options, features, and stability of Zen Cart®.',
                        'emphasized_text' => '',
                    ],
                ],
                'ownership_cta_label' => 'View Comparison',
                'ownership_cta_url' => '/compare',
                'cost_title' => 'The Real Cost of Selling',
                'cost_rows' => [
                    ['icon_class' => 'fa-solid fa-file-invoice-dollar', 'label' => 'Monthly License', 'value' => '$0.00'],
                ],
                'requirements_title' => 'System Requirements (v2.1+)',
                'requirements_rows' => [
                    ['icon_class' => 'fa-brands fa-php', 'label' => 'PHP', 'value' => '8.1 - 8.3+'],
                ],
                'requirements_body_title' => 'Getting Started',
                'requirements_body_paragraphs' => [
                    'A standard Linux-based hosting solution (LAMP stack) is usually all you need.',
                ],
                'requirements_cta_label' => 'View Full Server Requirements',
                'requirements_cta_url' => 'https://docs.zen-cart.com/user/first_steps/server_requirements/',
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('cms.templates.home')
            ->assertSee('Commerce software with room to grow.')
            ->assertSee('Get Started Now')
            ->assertSee('Deploy your website in as little as 10 minutes')
            ->assertSee('01. Download Zen Cart')
            ->assertSee('Ownership vs. Subscriptions')
            ->assertSee('The Real Cost of Selling')
            ->assertDontSee('FREE Open Source');
    }

    public function test_home_route_preserves_compare_copy_fidelity_for_structured_home_blocks(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'home'],
            [
                'custom_route' => '/',
                'title' => 'Home From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
            ]
        );

        $page->blocks()->delete();

        $page->blocks()->create([
            'type' => 'home_compare_section',
            'name' => 'Home Compare',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'The Open Source Choice',
                'title' => 'Ownership vs. Subscriptions',
                'body' => 'In a market saturated with "rented" storefronts, Zen Cart® stands as a bastion of true digital ownership. Don\'t let your platform dictate your profit.',
                'ownership_title' => 'Why Zen Cart® Over Commercial Platforms?',
                'ownership_paragraphs' => [
                    [
                        'text' => 'As for commercial SaaS programs? Watch as their fees ramp up to match your sales! At some point, every growing business is forced to "jump ship" to avoid these revenue taxes.',
                        'emphasized_text' => 'Why not start with an unrestricted solution from day one?',
                    ],
                ],
                'ownership_cta_label' => 'View Comparison',
                'ownership_cta_url' => '/compare',
                'cost_title' => 'The Real Cost of Selling',
                'cost_rows' => [
                    ['icon_class' => 'fa-solid fa-file-invoice-dollar', 'label' => 'Monthly License', 'value' => '$0.00'],
                ],
                'requirements_title' => 'System Requirements (v2.1+)',
                'requirements_rows' => [
                    ['icon_class' => 'fa-brands fa-php', 'label' => 'PHP', 'value' => '8.1 - 8.3+'],
                ],
                'requirements_body_title' => 'Getting Started',
                'requirements_body_paragraphs' => [
                    'A standard Linux-based hosting solution (LAMP stack) is usually all you need.',
                    'While many hosts offer "one-click" installers, we recommend a manual installation for production environments to ensure maximum security and customizability.',
                ],
                'requirements_cta_label' => 'View Full Server Requirements',
                'requirements_cta_url' => 'https://docs.zen-cart.com/user/first_steps/server_requirements/',
            ],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<strong>Why not start with an unrestricted solution from day one?</strong>', false);

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertMatchesRegularExpression(
            '/Getting Started<\/h3>\s*<div class="ui-body-copy mt-4 cms-markdown"><p>A standard Linux-based hosting solution \(LAMP stack\) is usually all you need\.\s*While many hosts offer (?:&quot;|&#34;|")one-click(?:&quot;|&#34;|") installers, we recommend a manual installation for production environments to ensure maximum security and customizability\.<\/p><\/div>\s*<a /',
            $content
        );
    }

    public function test_home_route_ignores_mixed_non_home_blocks_and_keeps_legacy_layout(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'home'],
            [
                'custom_route' => '/',
                'title' => 'Home From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
            ]
        );

        $page->blocks()->delete();

        $page->blocks()->create([
            'type' => 'feature_list',
            'name' => 'Unexpected Generic Block',
            'sort_order' => 10,
            'data' => [
                'intro' => 'This should not replace the homepage layout.',
                'items' => [['text' => 'Generic item']],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Commerce software with room to grow.')
            ->assertSee('Why Teams Still Choose Zen Cart')
            ->assertDontSee('This should not replace the homepage layout.');
    }

    public function test_home_route_requires_a_cms_page_and_does_not_fall_back_to_legacy_blade_view(): void
    {
        CmsPage::query()->where('slug', 'home')->delete();

        $this->get(route('home'))
            ->assertNotFound();
    }

    public function test_compare_route_renders_cms_page_using_compare_template(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'compare'],
            [
                'custom_route' => '/compare',
                'title' => 'Compare From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed Compare Title',
            ]
        );

        $page->blocks()->delete();

        $page->blocks()->create([
            'type' => 'comparison_matrix',
            'name' => 'Compare Hero',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'The Strategic Choice',
                'title' => 'CMS Compare Hero',
                'body' => 'Managed compare body.',
                'headers' => [
                    ['label' => 'Feature', 'emphasize' => false],
                    ['label' => 'Zen Cart', 'emphasize' => true],
                    ['label' => 'Shopify', 'emphasize' => false],
                ],
                'rows' => [
                    [
                        'feature' => 'Transaction Fees',
                        'values' => ['0%', '0.5% - 2.0%'],
                        'highlight_column' => 1,
                    ],
                ],
            ],
        ]);

        $page->blocks()->create([
            'type' => 'comparison_panel',
            'name' => 'Shopify Panel',
            'sort_order' => 20,
            'data' => [
                'eyebrow' => 'The Ownership',
                'title' => 'Zen Cart vs. Shopify',
                'paragraphs' => [
                    'Merchants keep control of their storefront and customer journey.',
                ],
                'checklist_items' => [
                    'No revenue tax.',
                ],
                'section_tone' => 'cream',
                'aside_type' => 'card',
                'aside_title' => 'Real Cost',
                'aside_theme' => 'dark',
                'image_position' => 'end',
                'aside_rows' => [
                    [
                        'label' => 'Monthly Fees',
                        'value' => '$39+',
                    ],
                ],
            ],
        ]);

        $this->get(route('compare'))
            ->assertOk()
            ->assertViewIs('cms.templates.compare')
            ->assertViewHas('page', fn (CmsPage $viewPage): bool => $viewPage->is($page))
            ->assertSee('<title>Managed Compare Title</title>', false)
            ->assertSee('CMS Compare Hero')
            ->assertSee('Managed compare body.')
            ->assertSee('Transaction Fees')
            ->assertSee('0.5% - 2.0%')
            ->assertSee('Zen Cart vs. Shopify')
            ->assertSee('No revenue tax.')
            ->assertSee('Monthly Fees')
            ->assertDontSee('bg-[#dddddf]', false);
    }

    public function test_compare_route_requires_a_cms_page_and_does_not_fall_back_to_legacy_blade_view(): void
    {
        CmsPage::query()->where('slug', 'compare')->delete();

        $this->get(route('compare'))
            ->assertNotFound();
    }

    public function test_comparison_alias_permanently_redirects_to_compare(): void
    {
        $this->get(route('comparison'))
            ->assertRedirect('/compare');

        $this->assertSame(301, $this->get('/comparison')->getStatusCode());
    }

    public function test_services_route_renders_cms_page_using_services_template(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'services'],
            [
                'custom_route' => '/services',
                'title' => 'Services From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed Services Title',
            ]
        );

        $page->blocks()->create([
            'type' => 'services_hero',
            'name' => 'Services Hero',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'Recommended',
                'title' => 'CMS Services Hero',
                'body' => 'Managed services body.',
            ],
        ]);

        $page->blocks()->create([
            'type' => 'link_grid',
            'name' => 'Service Categories',
            'sort_order' => 20,
            'data' => [
                'title' => 'Service Categories',
                'body' => '',
                'items' => [
                    [
                        'title' => 'Payment Processing',
                        'url' => '/services/payment-processing',
                        'body' => 'Collect money from your customers.',
                    ],
                ],
            ],
        ]);

        $this->get('/services')
            ->assertOk()
            ->assertViewIs('cms.templates.services')
            ->assertSee('<title>Managed Services Title</title>', false)
            ->assertSee('CMS Services Hero')
            ->assertSee('Managed services body.')
            ->assertSee('bg-[color:var(--color-site-panel)] py-12 sm:py-16', false)
            ->assertSee('Payment Processing')
            ->assertDontSee('cms-page-stack', false)
            ->assertDontSee('<div class="cms-page-shell">', false)
            ->assertDontSee('rounded-[28px]', false)
            ->assertDontSee('rounded-[24px]', false)
            ->assertDontSee('bg-gradient-to-r', false)
            ->assertDontSee('linear-gradient', false);
    }

    public function test_services_route_requires_a_cms_page_and_does_not_fall_back_to_legacy_blade_view(): void
    {
        CmsPage::query()->where('slug', 'services')->delete();

        $this->get('/services')
            ->assertNotFound();
    }

    public function test_service_detail_routes_render_cms_pages_using_service_detail_template(): void
    {
        foreach ([
            '/services/payment-processing' => 'Payment Processing',
            '/services/business-services' => 'Business Services',
            '/services/custom-programming' => 'Custom Programming',
        ] as $path => $title) {
            $this->get($path)
                ->assertOk()
                ->assertViewIs('cms.templates.service-detail')
                ->assertSee($title);
        }
    }

    public function test_service_detail_routes_require_cms_pages_and_do_not_fall_back_to_legacy_blade_views(): void
    {
        CmsPage::query()
            ->whereIn('slug', [
                'services-payment-processing',
                'services-business-services',
                'services-custom-programming',
            ])
            ->delete();

        $this->get('/services/payment-processing')->assertNotFound();
        $this->get('/services/business-services')->assertNotFound();
        $this->get('/services/custom-programming')->assertNotFound();
    }

    public function test_about_route_renders_cms_page_using_about_template(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'about'],
            [
                'custom_route' => '/about',
                'title' => 'About From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed About Title',
            ]
        );

        $page->blocks()->create([
            'type' => 'about_hero',
            'name' => 'About Hero',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'About The Project',
                'title' => 'CMS About Hero',
                'body' => 'Managed about body.',
            ],
        ]);

        $page->blocks()->create([
            'type' => 'icon_card_grid',
            'name' => 'Ecosystem',
            'sort_order' => 20,
            'data' => [
                'title' => 'Built for the Entire Ecosystem',
                'body' => 'Short intro copy.',
                'items' => [
                    [
                        'title' => 'For Developers',
                        'icon_class' => 'fa-solid fa-code',
                        'body' => 'Developer-friendly architecture.',
                    ],
                ],
            ],
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertViewIs('cms.templates.about')
            ->assertSee('<title>Managed About Title</title>', false)
            ->assertSee('CMS About Hero')
            ->assertSee('Managed about body.')
            ->assertSee('For Developers');
    }

    public function test_about_route_requires_a_cms_page_and_does_not_fall_back_to_legacy_blade_view(): void
    {
        CmsPage::query()->where('slug', 'about')->delete();

        $this->get('/about')
            ->assertNotFound();
    }

    public function test_donate_route_renders_cms_page_by_custom_route(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'donate'],
            [
                'custom_route' => '/donate',
                'title' => 'Donate From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
                'seo_title' => 'Managed Donate Title',
            ]
        );

        $page->blocks()->create([
            'type' => 'donation_hero',
            'name' => 'Donate Hero',
            'sort_order' => 10,
            'data' => [
                'eyebrow' => 'Support Zen Cart',
                'title' => 'CMS Donate Hero',
                'body' => 'Managed donate body.',
                'note' => 'Structured note.',
            ],
        ]);

        $page->blocks()->create([
            'type' => 'donation_method',
            'name' => 'PayPal',
            'sort_order' => 20,
            'data' => [
                'provider' => 'paypal',
                'eyebrow' => 'Flexible support',
                'title' => 'PayPal',
                'body' => 'Support the project every month or with a single contribution.',
                'cta_label' => 'Donate any amount via PayPal',
                'cta_url' => 'https://www.paypal.com/donate/?hosted_button_id=XFE9JURMSRAHW',
                'note' => 'Recurring amounts help the project plan ahead.',
                'tag' => '',
                'amounts' => ['5', '$7.50', 'abc'],
            ],
        ]);

        $this->get(route('donate'))
            ->assertOk()
            ->assertViewIs('cms.templates.donate')
            ->assertSee('<title>Managed Donate Title</title>', false)
            ->assertSee('CMS Donate Hero')
            ->assertSee('Managed donate body.')
            ->assertSee('bg-[color:var(--color-site-panel)] py-12 sm:py-16', false)
            ->assertSee('bg-[#fffdf5] py-12 sm:py-16', false)
            ->assertSee('Donate any amount via PayPal')
            ->assertSee('name="a3" value="5.00"', false)
            ->assertSee('name="a3" value="7.50"', false)
            ->assertSee('>$7.50</span>', false)
            ->assertSee('>$5</span>', false)
            ->assertDontSee('name="a3" value="0.00"', false)
            ->assertDontSee('.gif', false)
            ->assertDontSee('cms-page-stack', false)
            ->assertDontSee('bg-white py-12 sm:py-16', false)
            ->assertDontSee('bg-[color:var(--color-site-panel-strong)] py-12 sm:py-16', false)
            ->assertDontSee('<div class="cms-page-shell">', false)
            ->assertDontSee('rounded-[28px]', false)
            ->assertDontSee('rounded-[24px]', false)
            ->assertDontSee('bg-gradient-to-r', false)
            ->assertDontSee('linear-gradient', false);
    }

    public function test_donate_page_shows_no_preset_amount_buttons_when_the_cms_amounts_are_cleared(): void
    {
        $page = CmsPage::query()->where('slug', 'donate')->firstOrFail();
        $paypal = $page->blocks()->where('name', 'PayPal')->firstOrFail();
        $this->assertNotEmpty($paypal->data['amounts']);

        $paypal->update(['data' => array_merge($paypal->data, ['amounts' => []])]);

        $this->get(route('donate'))
            ->assertOk()
            ->assertSee('Donate any amount via PayPal')
            ->assertDontSee('name="a3"', false)
            ->assertSee('flex min-h-[10rem] flex-col items-center justify-center text-center lg:self-stretch', false)
            ->assertDontSee('Recurring Support')
            ->assertDontSee('mt-7 border-t border-[#ead9c6] pt-6', false);
    }

    public function test_donate_page_renders_paypals_donate_sdk_button_when_a_button_id_is_set(): void
    {
        $page = CmsPage::query()->where('slug', 'donate')->firstOrFail();
        $paypal = $page->blocks()->where('name', 'PayPal')->firstOrFail();

        $paypal->update(['data' => array_merge($paypal->data, ['donate_button_id' => 'WW3377SE3CEJU'])]);

        $this->get(route('donate'))
            ->assertOk()
            ->assertSee('https://www.paypalobjects.com/donate/sdk/donate-sdk.js', false)
            ->assertSee('PayPal.Donation.Button', false)
            ->assertSee("hosted_button_id: 'WW3377SE3CEJU'", false)
            ->assertSee('<noscript>', false);

        $paypal->update(['data' => array_merge($paypal->data, ['donate_button_id' => 'bad"});alert(1);//'])]);

        $this->get(route('donate'))
            ->assertOk()
            ->assertDontSee('donate-sdk.js', false)
            ->assertDontSee('alert(1)', false)
            ->assertSee('Donate any amount via PayPal');
    }

    public function test_donation_card_headings_are_cms_editable_and_keep_their_original_wording_by_default(): void
    {
        $page = CmsPage::query()->where('slug', 'donate')->firstOrFail();
        $paypal = $page->blocks()->where('name', 'PayPal')->firstOrFail();
        $zelle = $page->blocks()->where('name', 'Zelle')->firstOrFail();
        $withoutHeadings = fn (array $data): array => array_diff_key($data, ['amounts_heading' => 1, 'card_heading' => 1]);

        $paypal->update(['data' => $withoutHeadings($paypal->data)]);
        $zelle->update(['data' => $withoutHeadings($zelle->data)]);

        $this->get(route('donate'))
            ->assertSee('Recurring Support')
            ->assertSee('One-Time Donation')
            ->assertSee('Send To This Tag');

        $paypal->update(['data' => array_merge($paypal->data, ['amounts_heading' => 'Give monthly', 'card_heading' => 'Give once'])]);
        $zelle->update(['data' => array_merge($zelle->data, ['card_heading' => ''])]);

        $this->get(route('donate'))
            ->assertSee('Give monthly')
            ->assertSee('Give once')
            ->assertDontSee('Recurring Support')
            ->assertDontSee('One-Time Donation')
            ->assertDontSee('Send To This Tag');
    }

    public function test_donation_button_caption_tag_label_and_payment_description_are_cms_editable(): void
    {
        $page = CmsPage::query()->where('slug', 'donate')->firstOrFail();
        $paypal = $page->blocks()->where('name', 'PayPal')->firstOrFail();
        $zelle = $page->blocks()->where('name', 'Zelle')->firstOrFail();
        $without = fn (array $data, array $keys): array => array_diff_key($data, array_flip($keys));

        $paypal->update(['data' => $without($paypal->data, ['amount_suffix', 'item_name'])]);
        $zelle->update(['data' => $without($zelle->data, ['tag_label'])]);

        $this->get(route('donate'))
            ->assertSee('>a month</span>', false)
            ->assertSee('value="Buy the Zen Cart Team some coffee!"', false)
            ->assertSee('Zelle Tag');

        $paypal->update(['data' => array_merge($paypal->data, ['amount_suffix' => 'per month', 'item_name' => 'Support Zen Cart'])]);
        $zelle->update(['data' => array_merge($zelle->data, ['tag_label' => '', 'tag' => 'zc-donate'])]);

        $this->get(route('donate'))
            ->assertSee('>per month</span>', false)
            ->assertSee('value="Support Zen Cart"', false)
            ->assertSee('zc-donate')
            ->assertDontSee('>a month</span>', false)
            ->assertDontSee('Buy the Zen Cart Team some coffee!')
            ->assertDontSee('Zelle Tag');

        $paypal->update(['data' => array_merge($paypal->data, ['amount_suffix' => '', 'item_name' => ''])]);

        $this->get(route('donate'))
            ->assertDontSee('per month')
            ->assertSee('value="Buy the Zen Cart Team some coffee!"', false);
    }

    public function test_donate_route_requires_a_cms_page_and_does_not_fall_back_to_legacy_blade_view(): void
    {
        CmsPage::query()->where('slug', 'donate')->delete();

        $this->get(route('donate'))
            ->assertNotFound();
    }

    public function test_privacy_route_requires_a_cms_page_and_does_not_fall_back_to_blade_view(): void
    {
        CmsPage::query()->where('custom_route', '/privacy')->delete();

        $this->get(route('legal.privacy'))
            ->assertNotFound();
    }

    public function test_privacy_page_setting_can_link_to_cms_slug(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'privacy_page'],
            ['value' => 'privacy-alt', 'value_type' => 'page']
        );

        $page = CmsPage::query()->create([
            'slug' => 'privacy-alt',
            'title' => 'Configured Privacy Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $page->blocks()->create([
            'type' => 'raw_html',
            'name' => 'Configured Policy',
            'sort_order' => 10,
            'data' => ['html' => '<p>Configured privacy block text</p>'],
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Configured Privacy Page')
            ->assertSee('Configured privacy block text');
    }

    public function test_cookie_policy_route_renders_cms_page_by_custom_route(): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => 'cookie-policy'],
            [
                'custom_route' => '/cookie-policy',
                'title' => 'Cookie Policy From CMS',
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now()->subMinute(),
            ]
        );

        $page->blocks()->create([
            'type' => 'raw_html',
            'name' => 'Cookie Policy',
            'sort_order' => 10,
            'data' => ['html' => '<p>CMS cookie policy block text</p>'],
        ]);

        $this->get('/cookie-policy')
            ->assertOk()
            ->assertSee('Cookie Policy From CMS')
            ->assertSee('CMS cookie policy block text');
    }

    public function test_cookie_policy_page_setting_can_link_to_cms_slug(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'cookie_policy_page'],
            ['value' => 'cookie-policy-alt', 'value_type' => 'page']
        );

        $page = CmsPage::query()->create([
            'slug' => 'cookie-policy-alt',
            'title' => 'Configured Cookie Policy Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $page->blocks()->create([
            'type' => 'raw_html',
            'name' => 'Configured Cookie Policy',
            'sort_order' => 10,
            'data' => ['html' => '<p>Configured cookie policy block text</p>'],
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Configured Cookie Policy Page')
            ->assertSee('Configured cookie policy block text');
    }

    public function test_api_pages_list_uses_expected_contract_keys(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'contract-list-page',
            'title' => 'Contract List Page',
            'excerpt' => 'Excerpt',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'preview_token' => 'secret-preview-token',
        ]);

        $tag = CmsTag::query()->create([
            'name' => 'Contract',
            'slug' => 'contract',
        ]);
        $page->tags()->sync([$tag->id]);

        $payload = collect($this->getJson('/api/cms/pages')
            ->assertOk()
            ->json('data'))
            ->firstWhere('slug', $page->slug);

        $this->assertIsArray($payload);
        $this->assertArrayHasExactKeys($payload, [
            'id',
            'legacy_vbulletin_content_id',
            'slug',
            'custom_route',
            'title',
            'excerpt',
            'status',
            'published_at',
            'seo_title',
            'seo_description',
            'created_at',
            'updated_at',
            'tags',
        ]);
        $this->assertArrayNotHasKey('preview_token', $payload);
        $this->assertIsArray($payload['tags']);
        $this->assertArrayHasExactKeys($payload['tags'][0], [
            'id',
            'name',
            'slug',
            'pivot',
        ]);
    }

    public function test_api_page_single_uses_expected_contract_keys(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'contract-single-page',
            'title' => 'Contract Single Page',
            'excerpt' => 'Excerpt',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'preview_token' => 'hidden-token',
        ]);

        $page->blocks()->create([
            'type' => 'feature_list',
            'name' => 'Intro',
            'sort_order' => 10,
            'data' => ['content' => 'Hello'],
        ]);

        $tag = CmsTag::query()->create([
            'name' => 'Contract',
            'slug' => 'contract',
        ]);
        $page->tags()->sync([$tag->id]);

        $payload = $this->getJson('/api/cms/pages/'.$page->slug)
            ->assertOk()
            ->json('data');

        $this->assertIsArray($payload);
        $this->assertArrayHasExactKeys($payload, [
            'id',
            'legacy_vbulletin_content_id',
            'slug',
            'custom_route',
            'title',
            'excerpt',
            'status',
            'published_at',
            'seo_title',
            'seo_description',
            'created_at',
            'updated_at',
            'blocks',
            'tags',
        ]);
        $this->assertArrayNotHasKey('preview_token', $payload);
        $this->assertIsArray($payload['blocks']);
        $this->assertIsArray($payload['tags']);

        $this->assertArrayHasExactKeys($payload['blocks'][0], [
            'id',
            'cms_page_id',
            'slug',
            'type',
            'name',
            'sort_order',
            'is_reusable',
            'is_published',
            'data',
            'created_at',
            'updated_at',
        ]);
        $this->assertArrayHasExactKeys($payload['tags'][0], [
            'id',
            'name',
            'slug',
            'pivot',
        ]);
    }

    public function test_api_navigation_uses_expected_contract_keys(): void
    {
        CmsNavigation::query()->create([
            'name' => 'footer-contract-test',
            'items' => [
                ['label' => 'Downloads', 'url' => '/download', 'target' => '_self'],
            ],
        ]);

        $payload = $this->getJson('/api/cms/navigation/footer-contract-test')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($payload);
        $this->assertArrayHasExactKeys($payload, [
            'id',
            'name',
            'items',
            'created_at',
            'updated_at',
        ]);
        $this->assertIsArray($payload['items']);
        $this->assertArrayHasExactKeys($payload['items'][0], [
            'children',
            'label',
            'reference',
            'type',
            'url',
            'target',
        ]);
    }

    public function test_api_page_with_non_string_preview_query_is_rejected_as_not_found(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'preview-shape-test',
            'title' => 'Preview Shape Test',
            'status' => CmsPageStatus::DRAFT,
            'preview_token' => 'preview-shape-token',
        ]);

        $this->getJson('/api/cms/pages/'.$page->slug.'?preview[]=preview-shape-token')
            ->assertNotFound();

        $this->getJson('/api/cms/pages/'.$page->slug.'?preview[bad]=preview-shape-token')
            ->assertNotFound();
    }

    public function test_api_pages_tag_filter_handles_oversized_unmatched_input_with_stable_contract(): void
    {
        $this->getJson('/api/cms/pages?tag='.str_repeat('x', 4096))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function assertArrayHasExactKeys(array $payload, array $keys): void
    {
        $expected = $keys;
        $actual = array_keys($payload);

        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_published_page_with_custom_route_is_accessible_via_custom_route(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'about-us',
            'custom_route' => '/company/about',
            'title' => 'About Our Company',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/company/about')
            ->assertOk()
            ->assertSee('About Our Company');
    }

    public function test_custom_route_takes_precedence_over_slug_routing(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'our-services-page',
            'custom_route' => '/our-services',
            'title' => 'Our Services',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        // Custom route should work
        $this->get('/our-services')
            ->assertOk()
            ->assertSee('Our Services');

        // Slug route should still work
        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Our Services');
    }

    public function test_draft_page_with_custom_route_requires_preview_token(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'draft-page',
            'custom_route' => '/preview-only',
            'title' => 'Preview Only Content',
            'status' => CmsPageStatus::DRAFT,
            'preview_token' => 'draft-preview-token',
        ]);

        // Without preview token, custom route should return 404
        $this->get('/preview-only')
            ->assertNotFound();

        $this->get('/preview-only?preview=draft-preview-token')
            ->assertOk()
            ->assertSee('Preview Only Content');
    }

    public function test_page_without_custom_route_is_not_accessible_via_arbitrary_path(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'normal-page',
            'title' => 'Normal Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        // Should not be accessible via random path (would fall through to PageController)
        $this->get('/normal-page')
            ->assertNotFound(); // because the view doesn't exist

        // Should be accessible via slug route
        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Normal Page');
    }

    public function test_custom_route_with_nested_path_is_accessible(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'nested-page',
            'custom_route' => '/docs/guide/getting-started',
            'title' => 'Getting Started Guide',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/docs/guide/getting-started')
            ->assertOk()
            ->assertSee('Getting Started Guide');
    }

    public function test_multiple_pages_can_have_different_custom_routes(): void
    {
        $page1 = CmsPage::query()->create([
            'slug' => 'page-one',
            'custom_route' => '/route-one',
            'title' => 'Page One',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $page2 = CmsPage::query()->create([
            'slug' => 'page-two',
            'custom_route' => '/route-two',
            'title' => 'Page Two',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/route-one')
            ->assertOk()
            ->assertSee('Page One');

        $this->get('/route-two')
            ->assertOk()
            ->assertSee('Page Two');
    }

    public function test_custom_route_is_normalized_when_saved_without_leading_slash(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'normalized-custom-route',
            'custom_route' => 'docs/getting-started',
            'title' => 'Normalized Route',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertSame('/docs/getting-started', $page->fresh()?->custom_route);

        $this->get('/docs/getting-started')
            ->assertOk()
            ->assertSee('Normalized Route');
    }
}
