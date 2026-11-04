<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SiteHeaderLogoTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_header_shows_the_square_logo_on_small_screens_and_the_wide_logo_from_sm_up(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();
        $square = asset('assets/images/logo/ZCLogo150x150Trans.webp');
        $wide = asset('assets/images/logo/ZCLogo2026.webp');

        $this->assertMatchesRegularExpression(
            '#<picture>\s*<source media="\(min-width: 640px\)" srcset="'.preg_quote($wide, '#').'" width="275" height="100">\s*<img src="'.preg_quote($square, '#').'" alt="Zen Cart Logo" title="'.preg_quote(e(__('site.logo_title')), '#').'" width="150" height="150"#s',
            $html
        );
        $header = substr($html, (int) strpos($html, '<header'), 3000);
        $this->assertStringNotContainsString('ZCLogoStacked240t.png', $header);
        $this->assertStringNotContainsString('zen-cart-logo.png', $header);
        $this->assertFileExists(public_path('assets/images/logo/ZCLogo150x150Trans.webp'));
        $this->assertFileExists(public_path('assets/images/logo/ZCLogo2026.webp'));
    }
}
