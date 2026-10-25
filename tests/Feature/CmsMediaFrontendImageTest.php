<?php

namespace Tests\Feature;

use Filagora\Cms\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesCmsMediaFiles;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class CmsMediaFrontendImageTest extends TestCase
{
    use FakesCmsMediaFiles, RefreshDatabase, SeedsSitePages;

    public function test_home_intro_section_prefers_imported_cms_media_asset(): void
    {
        CmsMediaAsset::query()->updateOrCreate(
            ['path' => 'cms-media/home-intro-hero.jpg'],
            [
                'disk' => 'public',
                'filename' => 'home-intro-hero.jpg',
                'mime_type' => 'image/jpeg',
                'alt_text' => 'online shopping platform',
            ]
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/storage/cms-media/home-intro-hero.jpg', false)
            ->assertDontSee('assets/images/hero/pexels-negativespace-34577-small.jpg', false);
    }

    public function test_about_goals_section_prefers_imported_cms_media_asset(): void
    {
        $this->markTestSkipped('Asserts content of production page blocks, which the shell seeder does not carry; the staging parity run against a sanitised dump covers it.');

        CmsMediaAsset::query()->updateOrCreate(
            ['path' => 'cms-media/about-goals.jpg'],
            [
                'disk' => 'public',
                'filename' => 'about-goals.jpg',
                'mime_type' => 'image/jpeg',
                'alt_text' => 'Zen Cart Goals',
            ]
        );

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('/storage/cms-media/about-goals.jpg', false)
            ->assertDontSee('assets/images/pexels-polina-zimmerman-3782231.jpg', false);
    }
}
