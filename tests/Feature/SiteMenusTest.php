<?php

namespace Tests\Feature;

use Filagora\Cms\Models\CmsNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * A new site starts with the menus the live site has (a migration in the profile creates them), so
 * the header is not empty.
 */
class SiteMenusTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_a_new_site_has_the_five_menus(): void
    {
        $this->assertSame(
            ['footer_legal', 'footer_useful', 'forum_header', 'header', 'user_menu'],
            CmsNavigation::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_the_header_shows_the_top_menu_with_the_documentation_links_to_this_sites_docs(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Community Help')
            ->assertSee('Plugins')
            ->assertSee('Services')
            ->assertSee('Donate')
            ->assertSee('https://docs.zen-cart.com/user', false)
            ->assertSee('https://docs.zen-cart.com/dev', false);
    }

    public function test_the_footer_shows_its_menus_and_blocks(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Cookie Policy');
    }
}
