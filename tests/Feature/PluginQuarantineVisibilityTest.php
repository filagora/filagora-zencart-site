<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\PluginsSecurity\Enums\ScanSeverity as SecurityScanSeverity;
use Filagora\PluginsSecurity\Enums\ScanStatus as SecurityScanStatus;
use Filagora\PluginsSecurity\Enums\ScanTrigger as SecurityScanTrigger;
use Filagora\PluginsSecurity\Models\SecurityScan as PluginReleaseSecurityScan;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginQuarantineVisibilityTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private Plugin $plugin;

    private PluginRelease $release;

    protected function setUp(): void
    {
        parent::setUp();

        $type = PluginType::query()->create(['name' => 'Layout', 'slug' => 'layout', 'is_active' => true]);
        $this->plugin = Plugin::query()->create([
            'name' => 'Held Plugin',
            'slug' => 'held-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => User::factory()->create()->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $this->release = PluginRelease::query()->create([
            'plugin_id' => $this->plugin->id,
            'uploaded_by_id' => User::factory()->create()->id,
            'version' => '4.2.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/held-plugin.zip',
            'zip_filename' => 'held-plugin.zip',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $scan = PluginReleaseSecurityScan::query()->create([
            'plugin_release_id' => $this->release->id,
            'scanner_version' => 1,
            'status' => SecurityScanStatus::COMPLETED->value,
            'highest_severity' => SecurityScanSeverity::CRITICAL->value,
            'finding_counts' => ['critical' => 1],
            'triggered_by' => SecurityScanTrigger::UPLOAD->value,
            'finished_at' => now(),
        ]);
        $this->release->forceFill(['latest_security_scan_id' => $scan->id])->saveQuietly();

        $this->release->fresh()->forceFill(['status' => PluginStatus::QUARANTINED, 'quarantine_scan_id' => $scan->id, 'quarantined_at' => now()])->save();
        $this->release->refresh();
    }

    public function test_public_plugin_page_hides_a_quarantined_release(): void
    {
        $response = $this->get(route('plugins.show', $this->plugin));

        $response->assertOk();
        $response->assertDontSee('4.2.0');
        $response->assertDontSee('Quarantined by security scan');
    }

    public function test_public_download_of_a_quarantined_release_is_not_found(): void
    {
        $this->get(route('plugins.release.download', ['plugin' => $this->plugin, 'release' => $this->release]))
            ->assertNotFound();
    }

    public function test_moderator_sees_the_quarantine_notice_on_the_plugin_page(): void
    {
        $moderator = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');
        $this->grantEntitlement($moderator, 'operator.plugin.moderate');

        $response = $this->actingAs($moderator)->get(route('plugins.show', $this->plugin));

        $response->assertOk();
        $response->assertSee('4.2.0');
        $response->assertSee('Quarantined by security scan');
    }

    public function test_quarantined_release_is_absent_from_search(): void
    {
        $response = $this->get('/search?q=Held');

        $response->assertOk();
        $response->assertDontSee('Held Plugin 4.2.0');
    }

    public function test_quarantined_release_is_absent_from_the_api_release_list(): void
    {
        $response = $this->getJson("/api/v1/plugins/{$this->plugin->slug}/releases");

        $response->assertOk();
        $this->assertNotContains('4.2.0', collect($response->json('data') ?? [])->pluck('version')->all());
    }

    public function test_moderator_sees_the_security_scan_panel_with_findings(): void
    {
        $activeRelease = PluginRelease::query()->create([
            'plugin_id' => $this->plugin->id,
            'uploaded_by_id' => User::factory()->create()->id,
            'version' => '5.0.0',
            'internal_version' => 2,
            'zip_path' => 'plugins/held-plugin-5.zip',
            'zip_filename' => 'held-plugin-5.zip',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $scan = PluginReleaseSecurityScan::query()->create([
            'plugin_release_id' => $activeRelease->id,
            'scanner_version' => 7,
            'status' => SecurityScanStatus::COMPLETED->value,
            'highest_severity' => SecurityScanSeverity::MEDIUM->value,
            'finding_counts' => ['medium' => 1],
            'analyzer_versions' => ['taint' => ['version' => '1.2.3', 'status' => 'completed']],
            'findings' => [[
                'severity' => 'medium',
                'analyzer' => 'taint',
                'title' => 'Unescaped request value',
                'rule_id' => 'taint.echo.request',
                'file' => 'includes/thing.php',
                'line' => 42,
            ]],
            'triggered_by' => SecurityScanTrigger::MANUAL->value,
            'finished_at' => now(),
        ]);
        $activeRelease->forceFill(['latest_security_scan_id' => $scan->id])->saveQuietly();

        $this->get(route('plugins.show', $this->plugin))
            ->assertOk()
            ->assertSee('5.0.0')
            ->assertDontSee('Security scan')
            ->assertDontSee('taint.echo.request');

        $moderator = User::factory()->create();
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');
        $this->grantEntitlement($moderator, 'operator.plugin.moderate');

        $this->actingAs($moderator)
            ->get(route('plugins.show', $this->plugin))
            ->assertOk()
            ->assertSee('Security scan')
            ->assertSee('taint.echo.request')
            ->assertSee('includes/thing.php')
            ->assertSee('Full scan history in dashboard');
    }
}
