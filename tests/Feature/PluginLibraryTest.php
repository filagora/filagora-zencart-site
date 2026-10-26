<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffFrontendPreview;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginLibraryTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_plugins_index_loads_category_rows(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Payment Modules',
            'slug' => 'payment',
            'description' => 'Payment integrations',
            'is_active' => true,
            'position' => 10,
        ]);

        Plugin::query()->create([
            'name' => 'Stripe Payments',
            'slug' => 'stripe-payments',
            'plugin_type_id' => $type->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->get('/plugins');

        $response->assertOk();
        $response->assertSee('Plugin Library');
        $response->assertSee('class="ui-page-title"', false);
        $response->assertSee('Browse plugins by category and discover recent updates.');
        $response->assertSee('Payment Modules');
        // Regression guard: a stray leading "ost " once slipped in before
        // this view's @extends directive and rendered above the site header.
        $this->assertStringStartsWith('<!DOCTYPE html>', $response->getContent());
    }

    public function test_plugins_type_page_lists_plugins_in_category(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Shipping Modules',
            'slug' => 'shipping',
            'description' => 'Shipping integrations',
            'is_active' => true,
            'position' => 20,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'UPS Live Rates',
            'slug' => 'ups-live-rates',
            'plugin_type_id' => $type->id,
            'description' => 'Carrier rate support',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->get('/plugins/type/shipping');

        $response->assertOk();
        $response->assertSee('Files in category: Shipping Modules');
        $response->assertSee('Back to Plugin Library');
        $response->assertSee(route('plugins.index'), false);
        $response->assertSee($plugin->name);
    }

    public function test_plugins_type_page_truncates_long_plugin_descriptions_to_300_characters(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Shipping Modules',
            'slug' => 'shipping',
            'description' => 'Shipping integrations',
            'is_active' => true,
            'position' => 20,
        ]);

        $longDescription = str_repeat('a', 400);

        Plugin::query()->create([
            'name' => 'UPS Live Rates',
            'slug' => 'ups-live-rates',
            'plugin_type_id' => $type->id,
            'description' => $longDescription,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->get('/plugins/type/shipping');

        $response->assertOk();
        $response->assertDontSee($longDescription);
        $response->assertSee(str_repeat('a', 300).'...');
    }

    public function test_plugins_type_page_makes_the_whole_plugin_card_clickable(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Shipping Modules',
            'slug' => 'shipping',
            'description' => 'Shipping integrations',
            'is_active' => true,
            'position' => 20,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'UPS Live Rates',
            'slug' => 'ups-live-rates',
            'plugin_type_id' => $type->id,
            'description' => 'Carrier rate support',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->get('/plugins/type/shipping');

        $response->assertOk();
        $response->assertSee(
            'class="text-[#0d5fc8] hover:underline focus-visible:underline after:absolute after:inset-0 after:content-[\'\']" href="'.route('plugins.show', ['plugin' => $plugin->slug]).'"',
            false
        );
    }

    public function test_plugins_type_page_stripes_alternating_rows(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Shipping Modules',
            'slug' => 'shipping',
            'description' => 'Shipping integrations',
            'is_active' => true,
            'position' => 20,
        ]);

        Plugin::query()->create([
            'name' => 'Alpha Plugin',
            'slug' => 'alpha-plugin',
            'plugin_type_id' => $type->id,
            'description' => 'First row',
            'status' => PluginStatus::ACTIVE->value,
        ]);
        Plugin::query()->create([
            'name' => 'Beta Plugin',
            'slug' => 'beta-plugin',
            'plugin_type_id' => $type->id,
            'description' => 'Second row',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->get('/plugins/type/shipping?sort=name_asc');

        $response->assertOk();
        $response->assertSeeInOrder([
            'bg-white',
            'Alpha Plugin',
            'bg-[#fafbfc]',
            'Beta Plugin',
        ], false);
    }

    public function test_plugins_type_page_uses_forum_style_header_and_pagination(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools',
            'description' => 'Tools for store admins',
            'is_active' => true,
            'position' => 30,
        ]);

        foreach (range(1, 16) as $index) {
            Plugin::query()->create([
                'name' => 'Admin Tool '.$index,
                'slug' => 'admin-tool-'.$index,
                'plugin_type_id' => $type->id,
                'description' => 'Description '.$index,
                'status' => PluginStatus::ACTIVE->value,
            ]);
        }

        $response = $this->get('/plugins/type/admin-tools');

        $response->assertOk();
        $response->assertSee('Files in category: Admin Tools');
        $response->assertSee('Page 1 of 2');
        $response->assertSee('Previous');
        $response->assertSee('Next');
        $response->assertSee(route('plugins.type', ['slug' => 'admin-tools', 'page' => 2]), false);
        $this->assertSame(2, substr_count($response->getContent(), 'aria-label="Plugin category pagination"'));
        $this->assertSame(2, substr_count($response->getContent(), 'Page 1 of 2'));
    }

    public function test_plugins_index_and_type_page_use_clean_white_shells(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Design Tools',
            'slug' => 'design-tools',
            'description' => 'Design-oriented plugins',
            'is_active' => true,
            'position' => 35,
        ]);

        Plugin::query()->create([
            'name' => 'Visual Layout Builder',
            'slug' => 'visual-layout-builder',
            'plugin_type_id' => $type->id,
            'description' => 'Build layouts visually',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->get('/plugins')
            ->assertOk()
            ->assertSee('class="overflow-hidden border border-[#d8dce2] bg-white shadow-[0_2px_6px_rgba(15,23,42,0.04)]"', false)
            ->assertSee('<thead class="bg-white">', false);

        $this->get('/plugins/type/design-tools')
            ->assertOk()
            ->assertSee('class="mb-4 border border-[#d8dce2] bg-white p-4"', false)
            ->assertSee('class="grid grid-cols-[minmax(0,1fr)_130px_110px] gap-2.5 border-b border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#304458] max-sm:grid-cols-1"', false);
    }

    public function test_plugins_type_page_defaults_to_most_recent_first_and_can_sort_by_name(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Buttons and Graphics',
            'slug' => 'buttons-and-graphics-vb4',
            'description' => 'Graphics and buttons',
            'is_active' => true,
            'position' => 25,
        ]);

        $alphaPlugin = Plugin::query()->create([
            'name' => 'Alpha Buttons',
            'slug' => 'alpha-buttons',
            'plugin_type_id' => $type->id,
            'description' => 'Older release',
            'status' => PluginStatus::ACTIVE->value,
            'updated_at' => now()->subDays(20),
        ]);

        $zetaPlugin = Plugin::query()->create([
            'name' => 'Zeta Graphics',
            'slug' => 'zeta-graphics',
            'plugin_type_id' => $type->id,
            'description' => 'Newer release',
            'status' => PluginStatus::ACTIVE->value,
            'updated_at' => now()->subDays(2),
        ]);

        // created_at deliberately points the "wrong" way (Alpha's zip is far
        // older than Zeta's) to prove ordering follows approved_at, not the
        // release row's creation/upload date.
        PluginRelease::query()->create([
            'plugin_id' => $alphaPlugin->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/alpha.zip',
            'zip_filename' => 'alpha.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
            'approved_at' => now()->subDay(),
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $zetaPlugin->id,
            'version' => '2.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/zeta.zip',
            'zip_filename' => 'zeta.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
            'approved_at' => now()->subDays(20),
        ]);

        $defaultResponse = $this->get('/plugins/type/buttons-and-graphics-vb4');

        $defaultResponse->assertOk();
        $defaultResponse->assertSeeInOrder(['Alpha Buttons', 'Zeta Graphics']);
        $defaultResponse->assertSee('/plugins/type/buttons-and-graphics-vb4?sort=name_asc', false);
        $defaultResponse->assertSee('/plugins/type/buttons-and-graphics-vb4?sort=date_asc', false);
        $defaultResponse->assertSee('data-plugin-sort="date"', false);
        $defaultResponse->assertSee('data-plugin-sort-direction="desc"', false);
        $defaultResponse->assertSee('title="Sort by plugin name"', false);
        $defaultResponse->assertSee('title="Sort by plugin date"', false);
        $defaultResponse->assertSee('>↕<', false);

        $nameResponse = $this->get('/plugins/type/buttons-and-graphics-vb4?sort=name_asc');

        $nameResponse->assertOk();
        $nameResponse->assertSeeInOrder(['Alpha Buttons', 'Zeta Graphics']);
        $nameResponse->assertSee('/plugins/type/buttons-and-graphics-vb4?sort=name_desc', false);
        $nameResponse->assertSee('data-plugin-sort="name"', false);
        $nameResponse->assertSee('data-plugin-sort-direction="asc"', false);
    }

    public function test_plugins_type_page_displays_encapsulation_badges(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Payment Modules',
            'slug' => 'payment-modules',
            'description' => 'Payment integrations',
            'is_active' => true,
            'position' => 10,
        ]);

        $encapsulatedPlugin = Plugin::query()->create([
            'name' => 'Encapsulated Gateway',
            'slug' => 'encapsulated-gateway',
            'plugin_type_id' => $type->id,
            'description' => 'Encapsulated package',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $nonEncapsulatedPlugin = Plugin::query()->create([
            'name' => 'Legacy Gateway',
            'slug' => 'legacy-gateway',
            'plugin_type_id' => $type->id,
            'description' => 'Flat package',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $encapsulatedPlugin->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/encapsulated.zip',
            'zip_filename' => 'encapsulated.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'yes',
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $nonEncapsulatedPlugin->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/legacy.zip',
            'zip_filename' => 'legacy.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'no',
        ]);

        $response = $this->get('/plugins/type/payment-modules');

        $response->assertOk();
        $response->assertSee('Encapsulated');
        $response->assertSee('Not encapsulated');
    }

    public function test_plugins_type_page_no_longer_carries_the_old_packaging_filter(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools-filtered',
            'description' => 'Tools for admins',
            'is_active' => true,
            'position' => 15,
        ]);

        $encapsulatedPlugin = Plugin::query()->create([
            'name' => 'Encapsulated Audit',
            'slug' => 'encapsulated-audit',
            'plugin_type_id' => $type->id,
            'description' => 'Packaged for encapsulated installs',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $nonEncapsulatedPlugin = Plugin::query()->create([
            'name' => 'Legacy Audit',
            'slug' => 'legacy-audit',
            'plugin_type_id' => $type->id,
            'description' => 'Old-style package',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $encapsulatedPlugin->id,
            'version' => '2.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/encapsulated-audit.zip',
            'zip_filename' => 'encapsulated-audit.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'yes',
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $nonEncapsulatedPlugin->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/legacy-audit.zip',
            'zip_filename' => 'legacy-audit.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'no',
        ]);

        $response = $this->get('/plugins/type/admin-tools-filtered');

        // The old package-type dropdown is gone; packaging filtering now lives
        // on the unified plugin search page, which the filter bar submits to.
        $response->assertOk();
        $response->assertSee('Encapsulated Audit');
        $response->assertSee('Legacy Audit');
        $response->assertDontSee('Package type');
        $response->assertDontSee('id="plugin-encapsulated-filter"', false);
        $response->assertSee(route('plugins.search'), false);
    }

    public function test_plugin_show_page_lists_releases(): void
    {
        [$plugin, $owner] = $this->createPluginWithRelease(
            [],
            [
                'changelog' => "First stable release\nAdds Helcim support.",
                'download_count' => 12,
                'encapsulated' => 'yes',
                'zip_size' => 2048,
            ]
        );

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee($plugin->name);
        $response->assertSee('Author');
        $response->assertSee($owner->username ?? $owner->name ?? $owner->email);
        $response->assertSee('Download latest release');
        $response->assertSee('Release by');
        $response->assertSee('12 downloads');
        $response->assertSee('Encapsulated');
        $response->assertSee('2.0 KB ZIP');
        $response->assertSee('First stable release');
        $response->assertSee('Adds Helcim support.');
        $response->assertSee('rounded-pill border px-2.5 py-1 text-[11px] font-semibold uppercase', false);
        $response->assertSee('shrink-0 self-start', false);
    }

    public function test_plugin_show_page_exposes_the_numeric_plugin_id_with_a_copy_control(): void
    {
        [$plugin] = $this->createPluginWithRelease([], ['encapsulated' => 'yes']);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('Plugin ID');
        $response->assertSee('data-copy-plugin-id', false);
        $response->assertSee('data-copy-value="'.$plugin->id.'"', false);
        $response->assertSee('aria-label="Copy plugin ID '.$plugin->id.'"', false);
        $response->assertSee('<code>pluginId</code>', false);
    }

    public function test_plugin_show_page_shows_the_canonical_legacy_id_not_the_internal_key(): void
    {
        [$plugin] = $this->createPluginWithRelease([], ['encapsulated' => 'yes']);
        $plugin->update(['legacy_vbulletin_file_id' => 2431]);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('aria-label="Copy plugin ID 2431"', false);
        $response->assertSee('data-copy-value="2431"', false);
        $response->assertDontSee('aria-label="Copy plugin ID '.$plugin->id.'"', false);
    }

    public function test_plugin_show_page_omits_the_manifest_note_for_non_encapsulated_plugins(): void
    {
        [$plugin] = $this->createPluginWithRelease([], ['encapsulated' => 'no']);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('Plugin ID');
        $response->assertDontSee('<code>pluginId</code>', false);
    }

    public function test_plugin_show_page_exposes_each_release_id_with_a_copy_control(): void
    {
        [$plugin] = $this->createPluginWithRelease();
        $release = $plugin->releases()->firstOrFail();

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('Release ID');
        $response->assertSee('#'.$release->id, false);
        $response->assertSee('data-copy-value="'.$release->id.'"', false);
        $response->assertSee('aria-label="Copy release ID '.$release->id.'"', false);
    }

    public function test_plugin_show_page_shows_release_author_email_link_to_staff_only(): void
    {
        [$plugin, $owner] = $this->createPluginWithRelease();
        $owner->update(['email' => 'release-author@example.test']);

        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $regularViewer = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('mailto:release-author@example.test', false);

        $this->actingAs($regularViewer)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('mailto:release-author@example.test', false);

        $this->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('mailto:release-author@example.test', false);
    }

    public function test_plugin_show_page_preserves_line_breaks_in_overview_description(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools',
            'is_active' => true,
            'position' => 30,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Admin Audit Trail',
            'slug' => 'admin-audit-trail',
            'plugin_type_id' => $type->id,
            'description' => "First line of the overview.\nSecond line of the overview.",
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        // Regression test: the description is plain text (no Markdown
        // rendering), so without whitespace-pre-line the browser's default
        // white-space:normal collapses the newline and both lines run
        // together on one visual line.
        $response->assertSee('class="ui-body-copy mt-2 whitespace-pre-line"', false);
    }

    public function test_plugin_show_page_wraps_overview_and_release_notes_in_expandable_containers(): void
    {
        [$plugin] = $this->createPluginWithRelease(
            [],
            ['changelog' => str_repeat("A line of the release notes.\n", 20)]
        );
        $plugin->update(['description' => str_repeat("A line of the overview.\n", 20)]);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        // Both the overview and each release's notes are clamped to four
        // lines client-side; the toggle ships hidden and is only revealed by
        // JS when the text overflows. One wrapper for the overview, one for
        // the single release.
        $this->assertSame(2, substr_count($response->getContent(), 'data-description-expander'));
        $this->assertSame(2, substr_count($response->getContent(), 'data-description-body'));
        $response->assertSeeInOrder(['data-description-toggle', 'aria-expanded="false"', 'hidden'], false);
        $response->assertSee('>Show more</button>', false);
    }

    public function test_plugin_show_page_moves_stats_below_releases_and_uses_clean_shell(): void
    {
        [$plugin, $owner] = $this->createPluginWithRelease(
            [],
            ['created_at' => now()->subDay()]
        );

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Releases',
            '<div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Author</div>',
        ], false);
        $response->assertSee('class="mb-4 border border-[#d8dce2] bg-white p-4"', false);
        $response->assertSee('class="border border-[#d8dce2] bg-white p-3"', false);
        $response->assertSee($owner->username ?? $owner->name ?? $owner->email);
    }

    public function test_plugin_show_page_normalizes_legacy_repository_link_to_external_github_url(): void
    {
        [$plugin] = $this->createPluginWithRelease();
        $plugin->update([
            'github_repository_url' => 'zencart/example-plugin',
        ]);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('href="https://github.com/zencart/example-plugin"', false);
    }

    public function test_plugin_show_page_renders_repository_link_as_a_button_alongside_other_actions(): void
    {
        [$plugin] = $this->createPluginWithRelease();
        $plugin->update([
            'github_repository_url' => 'https://github.com/zencart/example-plugin',
        ]);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Download latest release',
            'class="inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="https://github.com/zencart/example-plugin" target="_blank"',
        ], false);
    }

    public function test_plugin_release_download_increments_download_count(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('plugins/download-count-release.zip', 'zip contents');

        [$plugin] = $this->createPluginWithRelease([], [
            'zip_path' => 'plugins/download-count-release.zip',
            'zip_filename' => 'download-count-release.zip',
            'download_count' => 3,
        ]);

        $this->get(route('plugins.download', $plugin))
            ->assertOk()
            ->assertDownload('download-count-release.zip');

        $this->assertDatabaseHas('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => '1.0.0',
            'download_count' => 4,
        ]);
    }

    public function test_plugin_show_page_lists_compatible_versions_instead_of_only_a_count(): void
    {
        [$plugin] = $this->createPluginWithRelease();

        $extraVersion = ZcVersion::query()->create([
            'version' => '2.1.0',
            'is_active' => true,
            'position' => 11,
        ]);

        $plugin->releases()->firstOrFail()->zcVersions()->attach($extraVersion->id);

        $response = $this->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('Compatible with 2.0.0, 2.1.0');
        $response->assertDontSee('compatible versions');
    }

    public function test_authenticated_user_sees_add_plugin_button_and_owner_sees_add_release_button(): void
    {
        [$plugin, $owner] = $this->createPluginWithRelease();
        $user = User::factory()->create();

        $indexResponse = $this->actingAs($user)->get('/plugins');
        $indexResponse->assertOk();
        $indexResponse->assertSee('Add Plugin');
        $indexResponse->assertSee(route('plugins.submit'), false);

        $showResponse = $this->actingAs($owner)->get(route('plugins.show', $plugin));
        $showResponse->assertOk();
        $showResponse->assertSee('Add new release');
        $showResponse->assertSee(route('plugins.release.new', $plugin), false);
    }

    public function test_paused_submissions_show_banner_and_hide_add_plugin_and_add_release_actions(): void
    {
        config(['plugins.submissions_enabled' => false]);
        [$plugin, $owner] = $this->createPluginWithRelease();

        $indexResponse = $this->actingAs($owner)->get('/plugins');
        $indexResponse->assertOk();
        $indexResponse->assertSee('temporarily paused');
        $indexResponse->assertDontSee('Add Plugin');
        $indexResponse->assertDontSee(route('plugins.submit'), false);

        $showResponse = $this->actingAs($owner)->get(route('plugins.show', $plugin));
        $showResponse->assertOk();
        $showResponse->assertSee('temporarily paused');
        $showResponse->assertDontSee('Add new release');
    }

    public function test_unverified_user_sees_verification_banner_and_no_plugin_submission_actions(): void
    {
        [$plugin] = $this->createPluginWithRelease();
        $user = User::factory()->unverified()->create();

        $indexResponse = $this->actingAs($user)->get('/plugins');
        $indexResponse->assertOk();
        $indexResponse->assertSee('Your email is not verified.');
        $indexResponse->assertSee('Verify email');
        $indexResponse->assertDontSee('Add Plugin');
        $indexResponse->assertDontSee(route('plugins.submit'), false);

        $showResponse = $this->actingAs($user)->get(route('plugins.show', $plugin));
        $showResponse->assertOk();
        $showResponse->assertSee('Your email is not verified.');
        $showResponse->assertSee('Verify email');
        $showResponse->assertDontSee('Add new release');
        $showResponse->assertDontSee(route('plugins.release.new', $plugin), false);
    }

    public function test_non_owner_non_staff_sees_add_release_button(): void
    {
        [$plugin] = $this->createPluginWithRelease();
        $user = User::factory()->create();

        $showResponse = $this->actingAs($user)->get(route('plugins.show', $plugin));

        $showResponse->assertOk();
        $showResponse->assertSee('Add new release');
        $showResponse->assertSee(route('plugins.release.new', $plugin), false);
    }

    public function test_staff_sees_moderation_queue_on_plugins_index(): void
    {
        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $type = PluginType::query()->create([
            'name' => 'Pending Type',
            'slug' => 'pending-type',
            'is_active' => true,
            'position' => 10,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Pending Plugin',
            'slug' => 'pending-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $staff->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $staff->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/pending.zip',
            'zip_filename' => 'pending.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $response = $this->actingAs($staff)->get('/plugins');

        $response->assertOk();
        $response->assertSee('Moderation Queue');
        $response->assertSee('Pending Plugin');
    }

    public function test_staff_preview_mode_hides_plugin_moderation_queue(): void
    {
        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $type = PluginType::query()->create([
            'name' => 'Pending Type',
            'slug' => 'pending-type-preview',
            'is_active' => true,
            'position' => 16,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Pending Plugin',
            'slug' => 'pending-plugin-preview',
            'plugin_type_id' => $type->id,
            'owner_id' => $staff->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $staff->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/pending-preview.zip',
            'zip_filename' => 'pending-preview.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $response = $this->actingAs($staff)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get('/plugins');

        $response->assertOk();
        $response->assertDontSee('Moderation Queue');
        $response->assertDontSee('Pending Plugin');
    }

    public function test_staff_sees_a_prominent_status_badge_and_no_moderation_form_for_a_permanently_rejected_release(): void
    {
        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        [$plugin, , $release] = $this->createPluginWithRelease(
            releaseOverrides: ['status' => PluginStatus::REJECTED_FINAL->value]
        );

        $response = $this->actingAs($staff)->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('Rejected');
        $response->assertSee("Rejected. Use the dashboard to change this release's status.", false);
        $response->assertDontSee('Approve Release');
        $response->assertDontSee('data-report-release-id="'.$release->id.'"', false);
    }

    public function test_staff_sees_approve_and_reject_controls_for_a_pending_release(): void
    {
        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        [$plugin] = $this->createPluginWithRelease(
            releaseOverrides: ['status' => PluginStatus::MODERATED->value]
        );

        $response = $this->actingAs($staff)->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('Approve Release');
        $response->assertSee('value="reject_permanently"', false);
        $response->assertDontSee("Rejected. Use the dashboard to change this release's status.");
    }

    public function test_staff_preview_mode_hides_plugin_moderation_controls_and_non_public_plugins(): void
    {
        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        [$publicPlugin] = $this->createPluginWithRelease();

        $publicResponse = $this->actingAs($staff)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('plugins.show', $publicPlugin));

        $publicResponse->assertOk();
        // Note: assertDontSee('Moderation Controls') alone would false-positive here —
        // the "Show Moderation Controls: On/Off" user-menu toggle label (present on
        // every staff page via the site header) contains that same substring, distinct
        // from this page's own moderation section heading.
        $publicResponse->assertDontSee('<h2 class="ui-content-title">Moderation Controls</h2>', false);
        $publicResponse->assertDontSee('Approve Release');
        $publicResponse->assertDontSee('Reject Release');

        $hiddenType = PluginType::query()->create([
            'name' => 'Hidden Type',
            'slug' => 'hidden-type-preview',
            'is_active' => true,
            'position' => 17,
        ]);

        $hiddenPlugin = Plugin::query()->create([
            'name' => 'Hidden Plugin',
            'slug' => 'hidden-plugin-preview',
            'plugin_type_id' => $hiddenType->id,
            'owner_id' => $staff->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $hiddenPlugin->id,
            'uploaded_by_id' => $staff->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/hidden-preview.zip',
            'zip_filename' => 'hidden-preview.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $this->actingAs($staff)
            ->withSession([StaffFrontendPreview::SESSION_KEY => false])
            ->get(route('plugins.show', $hiddenPlugin))
            ->assertNotFound();
    }

    public function test_staff_moderation_queue_does_not_duplicate_version_prefix(): void
    {
        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $type = PluginType::query()->create([
            'name' => 'Pending Type',
            'slug' => 'pending-type-version',
            'is_active' => true,
            'position' => 11,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'GDPR/DSAR Compliance',
            'slug' => 'gdpr-dsar-compliance',
            'plugin_type_id' => $type->id,
            'owner_id' => $staff->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $staff->id,
            'version' => 'v1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/gdpr.zip',
            'zip_filename' => 'gdpr.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $response = $this->actingAs($staff)->get('/plugins');

        $response->assertOk();
        $response->assertSee('GDPR/DSAR Compliance v1.0.0');
        $response->assertDontSee('GDPR/DSAR Compliance vv1.0.0');
    }

    public function test_staff_does_not_see_permanently_rejected_plugin_in_moderation_queue(): void
    {
        $staff = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $type = PluginType::query()->create([
            'name' => 'Final Reject Type',
            'slug' => 'final-reject-type',
            'is_active' => true,
            'position' => 13,
        ]);

        Plugin::query()->create([
            'name' => 'Final Rejected Plugin',
            'slug' => 'final-rejected-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $staff->id,
            'status' => PluginStatus::REJECTED_FINAL->value,
        ]);

        $response = $this->actingAs($staff)->get('/plugins');

        $response->assertOk();
        $response->assertDontSee('Final Rejected Plugin');
    }

    public function test_guest_is_redirected_to_login_modal_for_plugin_submission_pages(): void
    {
        $this->markTestSkipped('Needs the submission forms: slice S4b');

        [$plugin] = $this->createPluginWithRelease();

        $this->get(route('plugins.submit'))
            ->assertRedirect('/?auth=login&intended=%2Fplugins%2Fsubmit');

        $this->get(route('plugins.release.new', $plugin))
            ->assertRedirect('/?auth=login&intended=%2Fplugins%2F'.$plugin->slug.'%2Freleases%2Fnew');
    }

    public function test_download_redirects_with_error_when_file_missing(): void
    {
        [$plugin] = $this->createPluginWithRelease();

        $response = $this->get(route('plugins.download', $plugin));

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_error');
    }

    public function test_guest_is_redirected_to_login_modal_for_entitlement_gated_plugin_download(): void
    {
        [$plugin] = $this->createPluginWithRelease([
            'required_entitlement_scope' => 'plugins.commercial',
        ]);

        $this->get(route('plugins.download', $plugin))
            ->assertRedirect('/?auth=login&intended=%2Fplugins%2F'.$plugin->slug.'%2Fdownload');
    }

    public function test_authenticated_user_without_required_entitlement_cannot_download_gated_plugin(): void
    {
        [$plugin] = $this->createPluginWithRelease([
            'required_entitlement_scope' => 'plugins.commercial',
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('plugins.download', $plugin));

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_error', 'Downloading this plugin requires the plugins.commercial entitlement.');
    }

    public function test_entitled_user_can_download_gated_plugin_release(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('plugins/entitled-release.zip', 'zip contents');

        [$plugin] = $this->createPluginWithRelease([
            'required_entitlement_scope' => 'plugins.commercial',
        ], [
            'zip_path' => 'plugins/entitled-release.zip',
            'zip_filename' => 'entitled-release.zip',
        ]);

        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'plugins.commercial',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.download',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->get(route('plugins.download', $plugin))
            ->assertOk()
            ->assertDownload('entitled-release.zip');
    }

    public function test_entitled_bearer_token_with_plugins_download_ability_can_download_gated_plugin_release(): void
    {
        $this->markTestSkipped('Needs account API tokens: slice S5');

        Storage::fake('public');
        Storage::disk('public')->put('plugins/token-entitled-release.zip', 'zip contents');

        [$plugin] = $this->createPluginWithRelease([
            'required_entitlement_scope' => 'plugins.commercial',
        ], [
            'zip_path' => 'plugins/token-entitled-release.zip',
            'zip_filename' => 'token-entitled-release.zip',
        ]);

        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'plugins.commercial',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.download',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $token = $user->createToken('plugin-download', ['plugins:download']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->get(route('plugins.download', $plugin))
            ->assertOk()
            ->assertDownload('token-entitled-release.zip');
    }

    public function test_bearer_token_without_plugins_download_ability_is_forbidden_for_plugin_download(): void
    {
        $this->markTestSkipped('Needs account API tokens: slice S5');

        [$plugin] = $this->createPluginWithRelease();
        $user = User::factory()->create();
        $token = $user->createToken('plugin-download', ['plugins:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->get(route('plugins.download', $plugin))
            ->assertForbidden()
            ->assertSeeText(__('api.auth.token_ability_required'));
    }

    public function test_bearer_token_without_api_download_entitlement_is_forbidden_for_plugin_download(): void
    {
        $this->markTestSkipped('Needs account API tokens: slice S5');

        [$plugin] = $this->createPluginWithRelease();
        $user = User::factory()->create();
        $token = $user->createToken('plugin-download', ['plugins:download']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->get(route('plugins.download', $plugin))
            ->assertForbidden()
            ->assertSeeText(__('api.auth.token_entitlement_required'));
    }

    public function test_bearer_token_without_required_entitlement_is_forbidden_for_gated_plugin_download(): void
    {
        $this->markTestSkipped('Needs account API tokens: slice S5');

        [$plugin] = $this->createPluginWithRelease([
            'required_entitlement_scope' => 'plugins.commercial',
        ]);
        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.download',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
        $token = $user->createToken('plugin-download', ['plugins:download']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->get(route('plugins.download', $plugin))
            ->assertForbidden()
            ->assertSeeText(__('plugin.errors.download_entitlement_required', ['entitlement' => 'plugins.commercial']));
    }

    public function test_debug_mode_bypasses_bearer_token_download_entitlement_checks(): void
    {
        $this->markTestSkipped('Needs account API tokens: slice S5');

        config()->set('api.debug_bypass_access_checks', true);

        Storage::fake('public');
        Storage::disk('public')->put('plugins/debug-bypass-release.zip', 'zip contents');

        [$plugin] = $this->createPluginWithRelease([
            'required_entitlement_scope' => 'plugins.commercial',
        ], [
            'zip_path' => 'plugins/debug-bypass-release.zip',
            'zip_filename' => 'debug-bypass-release.zip',
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('plugin-download', ['forums:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->get(route('plugins.download', $plugin))
            ->assertOk()
            ->assertDownload('debug-bypass-release.zip');
    }

    public function test_invalid_bearer_token_is_unauthenticated_for_plugin_download(): void
    {
        $this->markTestSkipped('Needs account API tokens: slice S5');

        [$plugin] = $this->createPluginWithRelease();

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->get(route('plugins.download', $plugin))
            ->assertUnauthorized()
            ->assertSeeText('Unauthorized');
    }

    public function test_plugin_show_page_explains_locked_downloads_for_non_entitled_user(): void
    {
        [$plugin] = $this->createPluginWithRelease([
            'required_entitlement_scope' => 'plugins.commercial',
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertSee('Downloading this plugin requires the');
        $response->assertSee('plugins.commercial');
        $response->assertSee('Unlock download');
        $response->assertDontSee('Download latest release');
    }

    /**
     * @param  array<string, mixed>  $typeOverrides
     * @param  array<string, mixed>  $releaseOverrides
     * @return array{0: Plugin, 1: User, 2: PluginRelease}
     */
    private function createPluginWithRelease(array $typeOverrides = [], array $releaseOverrides = []): array
    {
        $owner = User::factory()->create();
        $type = PluginType::query()->create(array_merge([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools',
            'is_active' => true,
            'position' => 30,
        ], $typeOverrides));
        $version = ZcVersion::query()->create([
            'version' => '2.0.0',
            'is_active' => true,
            'position' => 10,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Admin Audit Trail',
            'slug' => 'admin-audit-trail',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $release = PluginRelease::query()->create(array_merge([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/missing.zip',
            'zip_filename' => 'missing.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ], $releaseOverrides));

        $release->zcVersions()->sync([$version->id]);

        return [$plugin, $owner, $release];
    }
}
