<?php

namespace Tests\Feature;

use Filagora\Cms\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesCmsMediaFiles;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ServicesPageCmsMediaTest extends TestCase
{
    use FakesCmsMediaFiles, RefreshDatabase, SeedsSitePages;

    public function test_services_page_prefers_partner_images_from_cms_media(): void
    {
        $this->markTestSkipped('Asserts content of production page blocks, which the shell seeder does not carry; the staging parity run against a sanitised dump covers it.');

        $paths = [
            'cms-media/partners/phpstorm.jpg',
            'cms-media/partners/DO_Powered_by_Badge_blue.png',
            'cms-media/partners/Navicat-Premium.png',
            'cms-media/partners/logoAtlassianPNG.png',
            'cms-media/partners/browserstack.svg',
            'cms-media/partners/tower2-logo-icon-subline-light.png',
        ];

        foreach ($paths as $path) {
            CmsMediaAsset::query()->updateOrCreate(
                ['path' => $path],
                [
                    'disk' => 'public',
                    'filename' => basename($path),
                    'mime_type' => 'image/'.pathinfo($path, PATHINFO_EXTENSION),
                ]
            );
        }

        $this->get('/services')
            ->assertOk()
            ->assertSee('/storage/cms-media/partners/phpstorm.jpg', false)
            ->assertSee('/storage/cms-media/partners/DO_Powered_by_Badge_blue.png', false)
            ->assertSee('/storage/cms-media/partners/Navicat-Premium.png', false)
            ->assertSee('/storage/cms-media/partners/logoAtlassianPNG.png', false)
            ->assertSee('/storage/cms-media/partners/browserstack.svg', false)
            ->assertSee('/storage/cms-media/partners/tower2-logo-icon-subline-light.png', false)
            ->assertDontSee('assets/images/partners/phpstorm.jpg', false)
            ->assertDontSee('assets/images/partners/DO_Powered_by_Badge_blue.png', false)
            ->assertDontSee('assets/images/partners/Navicat-Premium.png', false)
            ->assertDontSee('assets/images/partners/logoAtlassianPNG.png', false)
            ->assertDontSee('assets/images/partners/browserstack.svg', false)
            ->assertDontSee('assets/images/partners/tower2-logo-icon-subline-light.png', false);
    }

    public function test_pages_still_render_when_a_partner_image_is_missing_from_cms_media(): void
    {
        // Regression: the layout composer indexed the media map directly, so
        // one deleted partner image made every page using it fail.
        CmsMediaAsset::query()->where('path', 'cms-media/partners/phpstorm.jpg')->delete();

        $this->get('/services')->assertOk();
        $this->get(route('forums.index'))->assertOk();
    }
}
