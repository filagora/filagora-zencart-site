<?php

namespace Tests\Feature;

use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackToTopTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_pages_render_the_back_to_top_button(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'about-us',
            'title' => 'About Us',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('cms.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('data-back-to-top', false);
    }

    public function test_forum_index_renders_the_back_to_top_button(): void
    {
        $this->get(route('forums.index'))
            ->assertOk()
            ->assertSee('data-back-to-top', false);
    }

    public function test_pages_that_did_not_opt_in_do_not_render_the_button(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-back-to-top', false);
    }
}
