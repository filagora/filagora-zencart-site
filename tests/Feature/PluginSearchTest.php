<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\Plugins\Search\SyncReleaseDocuments;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginSearchTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_plugins_index_shows_a_search_box_pointing_at_the_search_page(): void
    {
        $this->get(route('plugins.index'))
            ->assertOk()
            ->assertSee(route('plugins.search'), false)
            ->assertSee('name="q"', false);
    }

    public function test_plugin_search_returns_more_than_eight_paginated_results(): void
    {
        $type = $this->createType();
        for ($i = 1; $i <= 25; $i++) {
            $this->createPluginWithRelease($type, ['name' => "Needle Widget {$i}"]);
        }

        $firstPage = $this->get(route('plugins.search', ['q' => 'Needle Widget']))->assertOk();
        $firstPage->assertSee('25 plugins found');
        $this->assertSame(20, substr_count($firstPage->getContent(), 'Needle Widget '));

        $secondPage = $this->get(route('plugins.search', ['q' => 'Needle Widget', 'page' => 2]))->assertOk();
        $this->assertSame(5, substr_count($secondPage->getContent(), 'Needle Widget '));
    }

    public function test_plugin_search_filters_by_zen_cart_version(): void
    {
        $type = $this->createType();
        $v210 = ZcVersion::query()->create(['version' => '2.1.0', 'is_active' => true, 'position' => 1]);
        $v158 = ZcVersion::query()->create(['version' => '1.5.8', 'is_active' => true, 'position' => 2]);

        [$modern, $modernRelease] = $this->createPluginWithRelease($type, ['name' => 'Modern Match Plugin']);
        $modernRelease->zcVersions()->sync([$v210->id]);
        SyncReleaseDocuments::dispatch('plugin', $modern->id);

        [$legacy, $legacyRelease] = $this->createPluginWithRelease($type, ['name' => 'Legacy Match Plugin']);
        $legacyRelease->zcVersions()->sync([$v158->id]);
        SyncReleaseDocuments::dispatch('plugin', $legacy->id);

        $this->get(route('plugins.search', ['q' => 'Match Plugin', 'zc_version' => '2.1.0']))
            ->assertOk()
            ->assertSee('Modern Match Plugin')
            ->assertDontSee('Legacy Match Plugin');
    }

    public function test_plugin_search_results_show_compatible_zen_cart_versions(): void
    {
        $type = $this->createType();
        $v300 = ZcVersion::query()->create(['version' => '3.0.0', 'is_active' => true, 'position' => 1]);
        $v210 = ZcVersion::query()->create(['version' => '2.1.0', 'is_active' => true, 'position' => 2]);

        [, $release] = $this->createPluginWithRelease($type, ['name' => 'Version Badge Plugin']);
        $release->zcVersions()->sync([$v300->id, $v210->id]);

        $this->get(route('plugins.search', ['q' => 'Version Badge Plugin']))
            ->assertOk()
            ->assertSee('Compatible with Zen Cart')
            ->assertSee('3.0.0')
            ->assertSee('2.1.0');
    }

    public function test_plugin_search_results_show_the_latest_release_date(): void
    {
        $type = $this->createType();
        [$plugin] = $this->createPluginWithRelease($type, ['name' => 'Dated Release Plugin']);

        // A legacy imported release can have no approved_at; the card must
        // still fall back to the release creation date rather than hiding it.
        $plugin->releases()->update(['approved_at' => null]);
        $plugin->releases()->update(['created_at' => now()->subDays(9)]);

        $this->get(route('plugins.search', ['q' => 'Dated Release Plugin']))
            ->assertOk()
            ->assertSee('latest release '.now()->subDays(9)->format('d M Y'));
    }

    public function test_plugin_search_filters_by_category(): void
    {
        $payments = $this->createType('Payments', 'payments');
        $shipping = $this->createType('Shipping', 'shipping');

        $this->createPluginWithRelease($payments, ['name' => 'Alpha Gateway Tool']);
        $this->createPluginWithRelease($shipping, ['name' => 'Alpha Courier Tool']);

        $this->get(route('plugins.search', ['q' => 'Alpha', 'type' => 'payments']))
            ->assertOk()
            ->assertSee('Alpha Gateway Tool')
            ->assertDontSee('Alpha Courier Tool');
    }

    public function test_plugin_search_filters_by_encapsulated_packaging(): void
    {
        $type = $this->createType();
        $this->createPluginWithRelease($type, ['name' => 'Beta Encapsulated Tool'], ['encapsulated' => 'yes']);
        $this->createPluginWithRelease($type, ['name' => 'Beta Legacy Tool'], ['encapsulated' => 'no']);

        $this->get(route('plugins.search', ['q' => 'Beta', 'encapsulated' => 'yes']))
            ->assertOk()
            ->assertSee('Beta Encapsulated Tool')
            ->assertDontSee('Beta Legacy Tool');
    }

    public function test_plugin_search_can_sort_by_downloads(): void
    {
        $type = $this->createType();
        $this->createPluginWithRelease($type, ['name' => 'Gamma Quiet Tool'], ['download_count' => 3]);
        $this->createPluginWithRelease($type, ['name' => 'Gamma Popular Tool'], ['download_count' => 9000]);

        $content = $this->get(route('plugins.search', ['q' => 'Gamma', 'sort' => 'downloads_desc']))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($content, 'Gamma Quiet Tool'),
            strpos($content, 'Gamma Popular Tool'),
        );
    }

    public function test_plugin_search_excludes_plugins_without_an_active_release(): void
    {
        $type = $this->createType();
        $this->createPluginWithRelease($type, ['name' => 'Delta Released Tool']);

        Plugin::query()->create([
            'name' => 'Delta Draft Tool',
            'slug' => 'delta-draft-tool-'.Str::lower(Str::random(6)),
            'plugin_type_id' => $type->id,
            'owner_id' => User::factory()->create()->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->get(route('plugins.search', ['q' => 'Delta']))
            ->assertOk()
            ->assertSee('Delta Released Tool')
            ->assertDontSee('Delta Draft Tool');
    }

    public function test_plugin_search_without_criteria_shows_a_prompt(): void
    {
        $this->get(route('plugins.search'))
            ->assertOk()
            ->assertSee('Enter a search term or choose a filter');
    }

    public function test_best_match_ordering_matches_the_header_search(): void
    {
        $type = $this->createType();
        $this->createPluginWithRelease($type, ['name' => 'Kappa Exact Widget']);
        $this->createPluginWithRelease($type, ['name' => 'Unrelated Tool', 'description' => 'mentions kappa widget in passing']);
        $this->createPluginWithRelease($type, ['name' => 'Kappa Widget Popular'], ['download_count' => 8000]);

        $pageOrder = $this->pluginSlugOrder(
            $this->get(route('plugins.search', ['q' => 'Kappa Widget']))->assertOk()->getContent()
        );
        $headerOrder = $this->pluginSlugOrder(
            $this->get('/search?q='.urlencode('Kappa Widget').'&type=plugin_release')->assertOk()->getContent()
        );

        $this->assertNotEmpty($headerOrder);
        // Full equality, not just a shared prefix: the two searches must
        // agree on which plugins match at all, not merely on the relative
        // order of whichever subset each happens to find. This is what
        // previously slipped through - the plugins page and header search
        // queried different indexes (Plugin vs PluginRelease) with
        // non-overlapping searchable fields, so "Unrelated Tool" (matched
        // only via its description) appeared on the plugins page but not in
        // header search, while a prefix comparison of the two orderings
        // still passed.
        $this->assertSame($headerOrder, $pageOrder);
    }

    /**
     * Regression: the plugins-page search used to query the `Plugin` Scout
     * index (name/description/slug only), which never included release-level
     * text at all - so a query matching only a release's version or
     * changelog silently found nothing here even though the header search
     * (querying the `PluginRelease` index) found it.
     */
    public function test_plugin_search_matches_release_changelog_text(): void
    {
        $type = $this->createType();
        [$plugin] = $this->createPluginWithRelease(
            $type,
            ['name' => 'Totally Unrelated Plugin Name'],
            ['changelog' => 'Fixes the omegawidget shipping calculation bug.'],
        );

        $this->get(route('plugins.search', ['q' => 'omegawidget']))
            ->assertOk()
            ->assertSee($plugin->name);
    }

    /**
     * @return list<string>
     */
    private function pluginSlugOrder(string $html): array
    {
        preg_match_all('#/plugins/(search-plugin-[a-z0-9]+)(?:["/?])#', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<string, mixed>  $pluginOverrides
     * @param  array<string, mixed>  $releaseOverrides
     * @return array{0: Plugin, 1: PluginRelease}
     */
    private function createPluginWithRelease(PluginType $type, array $pluginOverrides = [], array $releaseOverrides = []): array
    {
        $owner = User::factory()->create();

        $plugin = Plugin::query()->create(array_merge([
            'name' => 'Search Plugin '.Str::lower(Str::random(6)),
            'slug' => 'search-plugin-'.Str::lower(Str::random(10)),
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ], $pluginOverrides));

        $release = PluginRelease::query()->create(array_merge([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/search-'.Str::lower(Str::random(8)).'.zip',
            'zip_filename' => 'search.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
            'download_count' => 0,
            'approved_at' => now(),
        ], $releaseOverrides));

        return [$plugin, $release];
    }

    private function createType(string $name = 'Search Tools', string $slug = 'search-tools'): PluginType
    {
        return PluginType::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'position' => 10,
        ]);
    }
}
