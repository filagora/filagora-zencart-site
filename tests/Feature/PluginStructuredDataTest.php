<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginStructuredDataTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_a_public_plugin_page_emits_software_application_data_from_real_plugin_data(): void
    {
        $owner = User::factory()->create(['username' => 'plugin_author']);
        $plugin = $this->createPlugin($owner, [
            'name' => 'Cart Helper',
            'description' => "Speeds up the <b>cart</b> &amp; checkout.\n\nWorks with   many themes.",
        ]);
        $release = $this->createRelease($plugin, $owner, '2.3.1', ['2.1.0', '1.5.8', '2.0.0']);

        $data = $this->structuredData($this->get(route('plugins.show', $plugin))->assertOk()->getContent());

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('SoftwareApplication', $data['@type']);
        $this->assertSame('Cart Helper', $data['name']);
        $this->assertSame(route('plugins.show', $plugin), $data['url']);
        $this->assertSame('Speeds up the cart & checkout. Works with many themes.', $data['description']);
        $this->assertSame('Payment Modules', $data['applicationCategory']);
        $this->assertSame('2.3.1', $data['softwareVersion']);
        $this->assertSame('Zen Cart 1.5.8, 2.0.0, 2.1.0', $data['softwareRequirements']);
        $this->assertSame($plugin->created_at->toIso8601String(), $data['datePublished']);
        $this->assertSame($release->created_at->toIso8601String(), $data['dateModified']);
        $this->assertSame(['@type' => 'Person', 'name' => 'plugin_author'], $data['author']);

        foreach (['offers', 'aggregateRating', 'downloadUrl', 'installUrl'] as $absent) {
            $this->assertArrayNotHasKey($absent, $data);
        }

        $this->assertSame('Organization', $data['publisher']['@type']);
        $this->assertSame(route('home'), $data['publisher']['url']);
    }

    public function test_a_plugin_page_also_emits_a_breadcrumb_trail_through_its_type(): void
    {
        $plugin = $this->createPlugin(null, ['name' => 'Trail Plugin']);

        $crumbs = $this->structuredData($this->get(route('plugins.show', $plugin))->assertOk()->getContent(), 'BreadcrumbList');

        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Plugins', 'item' => route('plugins.index')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Payment Modules', 'item' => route('plugins.type', 'payment-modules')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Trail Plugin', 'item' => route('plugins.show', $plugin)],
        ], $crumbs['itemListElement']);
    }

    public function test_optional_fields_are_left_out_when_the_plugin_has_no_such_data(): void
    {
        $plugin = $this->createPlugin(null, ['description' => null]);

        $data = $this->structuredData($this->get(route('plugins.show', $plugin))->assertOk()->getContent());

        $this->assertSame('SoftwareApplication', $data['@type']);
        foreach (['description', 'softwareVersion', 'softwareRequirements', 'dateModified', 'author'] as $missing) {
            $this->assertArrayNotHasKey($missing, $data);
        }
    }

    public function test_long_descriptions_are_shortened(): void
    {
        $plugin = $this->createPlugin(null, ['description' => str_repeat('word ', 200)]);

        $data = $this->structuredData($this->get(route('plugins.show', $plugin))->assertOk()->getContent());

        $this->assertLessThanOrEqual(303, mb_strlen($data['description']));
        $this->assertStringEndsWith('...', $data['description']);
    }

    public function test_a_plugin_name_cannot_break_out_of_the_script_tag(): void
    {
        $plugin = $this->createPlugin(null, ['name' => 'Evil </script><script>alert(1)</script> & Co']);

        $html = $this->get(route('plugins.show', $plugin))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertSame('Evil </script><script>alert(1)</script> & Co', $this->structuredData($html)['name']);
    }

    public function test_a_plugin_that_is_not_public_emits_no_structured_data_even_for_staff(): void
    {
        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $pending = $this->createPlugin($staff, ['status' => PluginStatus::MODERATED->value, 'slug' => 'pending-structured']);

        $this->actingAs($staff)
            ->get(route('plugins.show', $pending))
            ->assertOk()
            ->assertDontSee('application/ld+json', false);
    }

    /**
     * Every JSON-LD document on the page, keyed by its @type.
     *
     * @return array<string, array<string, mixed>>
     */
    private function documents(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $documents = [];
        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);
            $documents[$decoded['@type']] = $decoded;
        }

        return $documents;
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(string $html, string $type = 'SoftwareApplication'): array
    {
        $documents = $this->documents($html);
        $this->assertCount(2, $documents, 'Expected the main document and a breadcrumb list.');
        $this->assertArrayHasKey($type, $documents);

        return $documents[$type];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPlugin(?User $owner, array $overrides = []): Plugin
    {
        $type = PluginType::query()->firstOrCreate(
            ['slug' => 'payment-modules'],
            ['name' => 'Payment Modules', 'is_active' => true, 'position' => 1]
        );

        return Plugin::query()->create(array_merge([
            'name' => 'Structured Plugin',
            'slug' => 'structured-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner?->id,
            'status' => PluginStatus::ACTIVE->value,
        ], $overrides));
    }

    /**
     * @param  list<string>  $zcVersions
     */
    private function createRelease(Plugin $plugin, User $uploader, string $version, array $zcVersions): PluginRelease
    {
        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => $version,
            'internal_version' => 1,
            'zip_path' => 'plugins/structured.zip',
            'zip_filename' => 'structured.zip',
            'zip_size' => 1024,
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'yes',
        ]);

        $release->zcVersions()->sync(collect($zcVersions)->map(
            fn (string $zc, int $index): int => ZcVersion::query()->create([
                'version' => $zc,
                'is_active' => true,
                'position' => $index + 1,
            ])->id
        )->all());

        return $release;
    }
}
