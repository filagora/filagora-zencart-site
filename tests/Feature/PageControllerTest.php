<?php

namespace Tests\Feature;

use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_an_encoded_slash_path_does_not_crash(): void
    {
        // Reproduces a real Honeybadger report: GET /%2f made Symfony's
        // router match this route while capturing zero parameters at all,
        // and Laravel's dependency resolver silently dropped the required,
        // default-less $page argument instead of supplying one, throwing
        // "too few arguments" (an ArgumentCountError -> 500) rather than
        // handling the request normally. $page now defaults to '', so this
        // resolves the same as a bare "/" would (the home page's
        // custom_route) instead of crashing.
        $this->get('/%2f')->assertOk();
    }

    public function test_catch_all_route_still_renders_a_published_cms_page_by_custom_route(): void
    {
        CmsPage::query()->create([
            'slug' => 'catch-all-target',
            'custom_route' => '/catch-all-target',
            'title' => 'Catch-All Target',
            'excerpt' => 'Reached via the fallback /{page} route',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/catch-all-target')
            ->assertOk()
            ->assertSee('Catch-All Target');
    }

    public function test_catch_all_route_404s_for_an_unknown_page(): void
    {
        $this->get('/no-such-page-exists')->assertNotFound();
    }
}
