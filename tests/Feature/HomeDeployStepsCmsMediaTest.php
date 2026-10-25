<?php

namespace Tests\Feature;

use Filagora\Cms\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesCmsMediaFiles;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class HomeDeployStepsCmsMediaTest extends TestCase
{
    use FakesCmsMediaFiles, RefreshDatabase, SeedsSitePages;

    public function test_home_deploy_section_uses_cms_media_step_images(): void
    {
        $paths = [
            'cms-media/steps/step_1.jpg',
            'cms-media/steps/step_2.jpg',
            'cms-media/steps/step_3.jpg',
            'cms-media/steps/step_4.jpg',
        ];

        foreach ($paths as $path) {
            CmsMediaAsset::query()->updateOrCreate(
                ['path' => $path],
                [
                    'disk' => 'public',
                    'filename' => basename($path),
                    'mime_type' => 'image/jpeg',
                ]
            );
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/storage/cms-media/steps/step_1.jpg', false)
            ->assertSee('/storage/cms-media/steps/step_2.jpg', false)
            ->assertSee('/storage/cms-media/steps/step_3.jpg', false)
            ->assertSee('/storage/cms-media/steps/step_4.jpg', false)
            ->assertDontSee('assets/images/steps/step_1.jpg', false)
            ->assertDontSee('assets/images/steps/step_2.jpg', false)
            ->assertDontSee('assets/images/steps/step_3.jpg', false)
            ->assertDontSee('assets/images/steps/step_4.jpg', false);
    }
}
