<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SitemapControllerTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_it_serves_the_generated_sitemap(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');

        $response = $this->get(route('sitemap.xml'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $this->assertStringContainsString('<urlset>', $response->streamedContent());
    }

    public function test_it_404s_when_no_sitemap_has_been_generated_yet(): void
    {
        Storage::fake('public');

        $response = $this->get(route('sitemap.xml'));

        $response->assertNotFound();
    }
}
