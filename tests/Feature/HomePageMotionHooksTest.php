<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class HomePageMotionHooksTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_home_page_renders_motion_hooks_for_reveals_and_parallax(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-home-motion-root', false)
            ->assertSee('data-home-reveal', false)
            ->assertSee('data-home-hero-reveal', false)
            ->assertSee('data-home-parallax', false)
            ->assertSee('Commerce software with room to grow.')
            ->assertSee('Open Source. Merchant Owned. Community Powered.')
            ->assertSee('ui-stat-card', false);
    }
}
