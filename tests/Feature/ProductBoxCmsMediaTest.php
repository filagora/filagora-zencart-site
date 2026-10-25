<?php

namespace Tests\Feature;

use Filagora\Cms\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesCmsMediaFiles;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ProductBoxCmsMediaTest extends TestCase
{
    use FakesCmsMediaFiles, RefreshDatabase, SeedsSitePages;

    public function test_product_box_image_renders_from_cms_media_on_all_frontend_views(): void
    {
        $this->markTestSkipped('Asserts content of production page blocks, which the shell seeder does not carry; the staging parity run against a sanitised dump covers it.');

        CmsMediaAsset::query()->updateOrCreate(
            ['path' => 'cms-media/zencart-box3-transparent.png'],
            [
                'disk' => 'public',
                'filename' => 'zencart-box3-transparent.png',
                'mime_type' => 'image/png',
                'alt_text' => 'Zen Cart product box',
            ]
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/storage/cms-media/zencart-box3-transparent.png', false)
            ->assertDontSee('assets/images/zencart_box3_transparent.png', false);

        $this->get(route('compare'))
            ->assertOk()
            ->assertSee('/storage/cms-media/zencart-box3-transparent.png', false)
            ->assertDontSee('assets/images/zencart_box3_transparent.png', false);

        $this->get(route('download'))
            ->assertOk()
            ->assertSee('/storage/cms-media/zencart-box3-transparent.png', false)
            ->assertDontSee('assets/images/zencart_box3_transparent.png', false);
    }
}
