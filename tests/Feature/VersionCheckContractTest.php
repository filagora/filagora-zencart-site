<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The parity gate for the plugin version check, one test group per cart generation that still
 * calls it (see lara-forum docs/plugin-versioncheck.md). These run against the site's own wiring:
 * the profile's routes, the `downloads.php` interception and the host middleware. A gate here that
 * turns a real store away is invisible until stores complain.
 */
class VersionCheckContractTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD_KEYS = [
        'id',
        'title',
        'latest_plugin_version',
        'latest_plugin_updated_date',
        'latest_plugin_updated_date_formatted',
        'filesize',
        'last_contributed_by',
        'support_thread',
        'encapsulated',
        'zcversions',
        'zcversions_csv',
        'link',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['filagora-zencart.versioncheck.cache_ttl_seconds' => 900]);
    }

    /** v1.5.4 and older: file_get_contents, no User-Agent, no redirects followed by some, no cookies kept. */
    public function test_v154_clients_get_the_payload_inline_from_downloads_php_with_no_user_agent(): void
    {
        $this->activePlugin(2431);

        $response = $this->withoutHeader('User-Agent')->get('/downloads.php?do=versioncheck&id=2431');

        $response->assertOk();
        $this->assertNotRedirect($response);
        $this->assertContractPayload($response, 2431);
        $this->assertSharedCacheable($response);
    }

    /** v1.5.5 / v1.5.6 reach this app only if the plugins host proxies; the path forms must still answer. */
    public function test_the_plugin_check_php_spellings_answer_without_credentials(): void
    {
        $this->activePlugin(2431);

        foreach (['/plugin_check.php?do=versioncheck&id=2431', '/plugin_check.php/versioncheck/2431'] as $url) {
            $response = $this->withHeader('User-Agent', 'Plugin Version Check [2431] example.com')->get($url);

            $response->assertOk();
            $this->assertContractPayload($response, 2431);
            $this->assertSharedCacheable($response);
        }
    }

    /** v1.5.7+: the server-manager ping server fetches this with its own User-Agent. */
    public function test_the_ping_server_fetch_is_answered(): void
    {
        $this->activePlugin(2431);

        $response = $this->withHeader('User-Agent', 'ZC Pingserver')->get('/plugincheck/2431');

        $response->assertOk();
        $this->assertContractPayload($response, 2431);
        $this->assertSharedCacheable($response);
    }

    public function test_an_unknown_plugin_is_an_empty_array_not_an_error_on_every_url(): void
    {
        foreach ([
            '/downloads.php?do=versioncheck&id=999999',
            '/plugin_check.php?do=versioncheck&id=999999',
            '/plugin_check.php/versioncheck/999999',
            '/plugincheck/999999',
        ] as $url) {
            $response = $this->get($url);

            $response->assertOk()->assertExactJson([]);
            $this->assertSharedCacheable($response);
        }
    }

    public function test_a_plugin_that_is_not_active_is_an_empty_array(): void
    {
        $this->activePlugin(2431, PluginStatus::MODERATED);

        $this->get('/plugincheck/2431')->assertOk()->assertExactJson([]);
    }

    public function test_the_downloads_php_check_is_rate_limited_per_plugin(): void
    {
        $this->activePlugin(2431);
        RateLimiter::clear('legacy-plugin-check:minute:legacy-plugin-check:ip:127.0.0.1:id:2431');
        RateLimiter::clear('legacy-plugin-check:hour:legacy-plugin-check:ip:127.0.0.1:id:2431');

        foreach (range(1, 20) as $ignored) {
            $this->get('/downloads.php?do=versioncheck&id=2431')->assertOk();
        }

        $this->get('/downloads.php?do=versioncheck&id=2431')->assertTooManyRequests();
    }

    private function assertNotRedirect(TestResponse $response): void
    {
        $this->assertFalse($response->isRedirection(), 'Older carts cannot follow a redirect.');
    }

    private function assertContractPayload(TestResponse $response, int $legacyId): void
    {
        $response->assertJsonCount(1);
        $row = $response->json('0');

        $this->assertSame(self::PAYLOAD_KEYS, array_keys($row), 'Deployed carts read exactly these keys; do not add, drop or reorder them.');
        $this->assertSame((string) $legacyId, $row['id']);
        $this->assertSame('Contract Plugin', $row['title']);
        $this->assertSame('v1.0.0', $row['latest_plugin_version']);
        $this->assertSame('1234', $row['filesize']);
        $this->assertSame('1', $row['encapsulated']);
        $this->assertIsArray($row['zcversions']);
        $this->assertStringStartsWith('http', $row['link']);

        foreach (['id', 'title', 'latest_plugin_version', 'latest_plugin_updated_date', 'filesize', 'encapsulated'] as $stringKey) {
            $this->assertIsString($row[$stringKey], "{$stringKey} must stay a string.");
        }
    }

    /** Shared caches may hold it, and a cookie would stop them. */
    private function assertSharedCacheable(TestResponse $response): void
    {
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('900', $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame('900', $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertCount(0, $response->headers->getCookies(), 'A Set-Cookie defeats shared caching.');
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    private function activePlugin(int $legacyId, PluginStatus $status = PluginStatus::ACTIVE): void
    {
        $owner = User::factory()->create(['username' => 'contract-author', 'email_verified_at' => now()]);
        $type = PluginType::query()->create([
            'name' => 'Contract Type',
            'slug' => 'contract-type',
            'is_active' => true,
            'position' => 10,
        ]);
        $plugin = Plugin::query()->create([
            'legacy_vbulletin_file_id' => $legacyId,
            'name' => 'Contract Plugin',
            'slug' => 'contract-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => $status->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => '1.0.0',
            'zip_path' => 'plugins/contract.zip',
            'zip_filename' => 'contract.zip',
            'zip_size' => 1234,
            'status' => $status->value,
            'encapsulated' => 'yes',
        ]);
    }
}
