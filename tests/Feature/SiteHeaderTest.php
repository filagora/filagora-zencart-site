<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SiteHeaderTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_header_row_stays_single_line_on_narrow_screens_so_the_fixed_menu_panels_clear_it(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('max-sm:flex-nowrap', false)
            ->assertSee('id="mobile-nav-panel" class="ui-shell-panel fixed right-4 top-[88px]', false);
    }

    public function test_home_page_title_is_short_and_not_the_long_tagline(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Zen Cart Support', false)
            ->assertDontSee('Putting the dream of your own business', false);
    }
}
