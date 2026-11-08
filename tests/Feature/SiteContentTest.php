<?php

namespace Tests\Feature;

use Filagora\Cms\Models\CmsBlock;
use Filagora\Cms\Models\CmsMediaAsset;
use Filagora\Cms\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A new site starts with the live site's pages and pictures: the profile's migration creates them from
 * database/content. Nothing here seeds anything.
 */
class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_site_has_the_eleven_pages_with_their_routes(): void
    {
        $expected = ['/' => 'home', '/about' => 'about', '/cookie-policy' => 'cookie-policy', '/compare' => 'compare', '/donate' => 'donate', '/download' => 'download', '/privacy' => 'privacy', '/services' => 'partners', '/services/business-services' => 'services-business-services', '/services/custom-programming' => 'services-custom-programming', '/services/payment-processing' => 'services-payment-processing'];
        ksort($expected);

        $this->assertSame($expected, CmsPage::query()->orderBy('custom_route')->pluck('slug', 'custom_route')->all());
    }

    public function test_every_page_is_published_and_has_its_blocks(): void
    {
        $this->assertSame(11, CmsPage::query()->where('status', 'published')->count());
        $this->assertSame(47, CmsBlock::query()->whereNotNull('cms_page_id')->where('is_draft', false)->where('is_published', true)->count());

        foreach (CmsPage::query()->get() as $page) {
            $this->assertGreaterThan(0, CmsBlock::query()->where('cms_page_id', $page->id)->count(), $page->slug);
        }
    }

    public function test_every_page_renders(): void
    {
        foreach (CmsPage::query()->pluck('custom_route') as $route) {
            $this->get($route)->assertOk();
        }
    }

    public function test_the_home_page_shows_its_pictures_and_the_footer_its_logo(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/storage/cms-media/home-intro-hero.jpg', false)
            ->assertSee('/storage/cms-media/steps/step_1.jpg', false)
            ->assertSee('/storage/cms-media/footer-github-logo.png', false);
    }

    public function test_every_picture_the_blocks_use_is_a_file_on_the_public_disk_and_in_the_media_library(): void
    {
        $references = [];

        foreach (CmsBlock::query()->get() as $block) {
            preg_match_all('#(?:/storage/)?(cms-media/[^"\\s<>]+)#', str_replace('\\/', '/', (string) json_encode($block->data)), $matches);
            $references = [...$references, ...$matches[1]];
        }

        $references = array_unique($references);

        $this->assertNotEmpty($references);

        foreach ($references as $path) {
            $path = stripslashes($path);
            $this->assertTrue(Storage::disk('public')->exists($path), "{$path} is not on the public disk");
            $this->assertTrue(CmsMediaAsset::query()->where('path', $path)->exists(), "{$path} is not in the media library");
        }
    }

    public function test_the_environment_addresses_are_filled_in_and_none_is_left_as_a_placeholder(): void
    {
        $everything = json_encode([CmsPage::query()->get()->toArray(), CmsBlock::query()->get()->toArray()]);

        $this->assertStringNotContainsString('{docs}', $everything);
        $this->assertStringNotContainsString('{site}', $everything);
        $this->assertStringNotContainsString('{support_email}', $everything);
        $this->assertStringContainsString('https://docs.zen-cart.com', str_replace('\\/', '/', $everything));
    }

    public function test_the_policy_pages_link_to_the_support_address_only_when_there_is_one(): void
    {
        // The test environment has no support address, so the "contact us" is plain text rather than a dead link.
        $html = (string) json_encode(CmsBlock::query()->whereIn('cms_page_id', CmsPage::query()->whereIn('slug', ['privacy', 'cookie-policy'])->pluck('id'))->pluck('data')->all());

        $this->assertStringContainsString('contact us', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }

    public function test_the_compare_page_has_no_dead_demo_button_and_the_home_title_is_short(): void
    {
        $this->assertStringNotContainsString('demo.html', json_encode(CmsBlock::query()->pluck('data')->all()));
        $this->assertSame('Zen Cart Support', CmsPage::query()->where('slug', 'home')->value('seo_title'));
    }
}
