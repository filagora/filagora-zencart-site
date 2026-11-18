<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The marketplace API docs: the plugins package's OpenAPI description with this site's title, address
 * and Zen Cart fields laid over it. The package tests that it describes every route it registers.
 */
class PluginMarketplaceApiDocsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function servedSpec(): array
    {
        $response = $this->get(route('docs.api.plugin-marketplace.spec'));

        $response->assertOk();

        return Yaml::parse($response->getContent());
    }

    public function test_plugin_marketplace_api_docs_page_is_available(): void
    {
        $response = $this->get(route('docs.api.plugin-marketplace'));

        $response->assertOk()
            ->assertSee('Plugin Marketplace API')
            ->assertSee(route('docs.api.plugin-marketplace.spec'), false);
    }

    public function test_the_docs_page_loads_only_scripts_from_this_site_because_the_security_policy_allows_no_other_for_it(): void
    {
        // The site's Content-Security-Policy (sent by the web server) allows scripts from a short list of
        // hosts, and Redoc's CDN is not on it: loaded from there, the page drew nothing but its header.
        $html = $this->get(route('docs.api.plugin-marketplace'))->getContent();

        preg_match_all('/<script[^>]+src="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $src) {
            $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/', $src, "{$src} is not served from this site.");
        }

        $this->assertFileExists(public_path('assets/js/redoc/redoc.standalone-2.5.4.js'));
        $this->assertStringContainsString('redoc.standalone-2.5.4.js', $html);
    }

    public function test_plugin_marketplace_openapi_spec_route_returns_yaml(): void
    {
        $response = $this->get(route('docs.api.plugin-marketplace.spec'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/yaml; charset=UTF-8')
            ->assertSee('openapi: 3.1.0')
            ->assertSee('/api/v1/plugins:');
    }

    public function test_the_spec_url_has_no_file_extension_because_the_web_server_answers_those_itself(): void
    {
        // On production nginx answers a .yaml path with its own 404 before the request reaches PHP, so
        // the docs page, which loads the spec from this URL, showed nothing. An extensionless path
        // reaches the application.
        $this->assertSame('', pathinfo((string) parse_url(route('docs.api.plugin-marketplace.spec'), PHP_URL_PATH), PATHINFO_EXTENSION));
    }

    public function test_the_spec_describes_the_api_routes_the_site_really_serves_and_only_those(): void
    {
        $documented = [];

        foreach ($this->servedSpec()['paths'] as $path => $item) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($item[$method])) {
                    $documented[] = $method.' '.$path;
                }
            }
        }

        $served = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with((string) $route->getName(), 'filagora-plugins.api.'))
            ->flatMap(fn (LaravelRoute $route): array => collect($route->methods())
                ->reject(fn (string $method): bool => in_array($method, ['HEAD', 'OPTIONS'], true))
                ->map(fn (string $method): string => strtolower($method).' /'.$route->uri())
                ->all())
            ->all();

        sort($documented);
        sort($served);

        $this->assertNotEmpty($served);
        $this->assertSame($served, $documented);
    }

    public function test_the_spec_carries_this_sites_title_address_and_zen_cart_fields(): void
    {
        $spec = $this->servedSpec();
        $schemas = $spec['components']['schemas'];

        $this->assertSame('Zen Cart Plugin Marketplace API', $spec['info']['title']);
        $this->assertSame(rtrim(config('app.url'), '/'), $spec['servers'][0]['url']);
        $this->assertStringNotContainsString('example.com', (string) json_encode($spec));
        $this->assertStringNotContainsString('the package registers', $spec['info']['description']);
        $this->assertStringContainsString('Who may call', $spec['info']['description']);

        $this->assertArrayHasKey('legacy_vbulletin_file_id', $schemas['Plugin']['properties']);
        $this->assertArrayHasKey('zip_root', $schemas['Plugin']['properties']);
        $this->assertArrayHasKey('zc_versions', $schemas['Release']['properties']);
        $this->assertSame(['yes', 'no', 'unknown'], $schemas['Release']['properties']['encapsulated']['enum']);
        $this->assertSame('string', $schemas['Release']['properties']['compatibility']['items']['type']);

        $upload = $spec['paths']['/api/v1/plugins/{slug}/releases']['post']['requestBody']['content']['multipart/form-data']['schema']['properties'];

        $this->assertArrayHasKey('zc_versions', $upload);
        $this->assertArrayHasKey('encapsulated', $upload);
        $this->assertArrayHasKey('zip_file', $upload, 'the package\'s own upload fields are kept');
    }

    public function test_laying_the_overlay_over_the_package_keeps_the_documents_structure_valid(): void
    {
        $spec = $this->servedSpec();

        // An empty requirement (a guest may call) must stay an object, not become a list.
        $this->assertSame([], $spec['paths']['/api/v1/plugins']['get']['security'][0]);
        $this->assertSame(['plugins:read'], $spec['paths']['/api/v1/plugins']['get']['security'][1]['bearer']);

        $refs = [];
        array_walk_recursive($spec, function ($value, $key) use (&$refs): void {
            if ($key === '$ref') {
                $refs[] = $value;
            }
        });

        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            $node = $spec;

            foreach (explode('/', ltrim($ref, '#/')) as $part) {
                $this->assertArrayHasKey($part, $node, "{$ref} does not resolve.");
                $node = $node[$part];
            }
        }
    }
}
