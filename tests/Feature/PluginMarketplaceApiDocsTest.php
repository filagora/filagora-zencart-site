<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PluginMarketplaceApiDocsTest extends TestCase
{
    public function test_plugin_marketplace_api_docs_page_is_available(): void
    {
        $response = $this->get(route('docs.api.plugin-marketplace'));

        $response->assertOk()
            ->assertSee('Plugin Marketplace API')
            ->assertSee(route('docs.api.plugin-marketplace.spec'), false)
            ->assertSee('cdn.redoc.ly', false);
    }

    public function test_plugin_marketplace_openapi_spec_route_returns_yaml(): void
    {
        $response = $this->get(route('docs.api.plugin-marketplace.spec'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/yaml; charset=UTF-8')
            ->assertSee('openapi: 3.1.0')
            ->assertSee('/api/v1/plugins:');
    }

    public function test_the_openapi_spec_documents_core_paths_the_site_really_serves(): void
    {
        $spec = (string) file_get_contents(base_path('docs/openapi-plugin-marketplace.yaml'));

        $this->assertStringContainsString('openapi: 3.1.0', $spec);
        $this->assertStringContainsString('title: Zen Cart Plugin Marketplace API', $spec);

        preg_match_all('#^  (/api/v1/[^\s:]+):#m', $spec, $matches);
        $documented = $matches[1];
        $served = collect(Route::getRoutes()->getRoutes())->map(fn ($route): string => '/'.$route->uri())->all();

        foreach (['/api/v1/plugin-types', '/api/v1/plugins', '/api/v1/plugins/{slug}', '/api/v1/plugins/{slug}/releases', '/api/v1/plugins/{slug}/releases/{version}', '/api/v1/plugins/{slug}/download/{version}', '/api/v1/plugins/updates'] as $path) {
            $this->assertContains($path, $documented);
        }

        foreach ($documented as $path) {
            $this->assertContains($path, $served, "{$path} is documented but not served.");
        }
    }
}
