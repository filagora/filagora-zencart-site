<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginReleaseZcVersionsUpdateTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    /**
     * @return array{
     *     owner: User,
     *     uploader: User,
     *     plugin: Plugin,
     *     oldRelease: PluginRelease,
     *     latestRelease: PluginRelease,
     *     zc157: ZcVersion,
     *     zc158: ZcVersion,
     *     zc200: ZcVersion,
     *     inactiveZc: ZcVersion,
     * }
     */
    private function makePlugin(): array
    {
        $owner = User::factory()->create();
        $uploader = User::factory()->create();

        $type = PluginType::query()->create([
            'name' => 'Layout',
            'slug' => 'layout',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Slideshow Manager',
            'slug' => 'slideshow-manager',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $zc157 = ZcVersion::query()->create(['version' => '1.5.7', 'is_active' => true, 'position' => 1]);
        $zc158 = ZcVersion::query()->create(['version' => '1.5.8', 'is_active' => true, 'position' => 2]);
        $zc200 = ZcVersion::query()->create(['version' => '2.0.0', 'is_active' => true, 'position' => 3]);
        $inactiveZc = ZcVersion::query()->create(['version' => '1.3.9', 'is_active' => false, 'position' => 0]);

        $oldRelease = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => '2.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/slideshow-20.zip',
            'zip_filename' => 'slideshow-20.zip',
            'changelog' => 'Original release.',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $oldRelease->forceFill(['created_at' => now()->subYear()])->saveQuietly();
        $oldRelease->zcVersions()->sync([$zc157->id]);

        $latestRelease = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => '3.0.0',
            'internal_version' => 2,
            'zip_path' => 'plugins/slideshow-300.zip',
            'zip_filename' => 'slideshow-300.zip',
            'changelog' => 'Current release.',
            'encapsulated' => 'yes',
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $latestRelease->zcVersions()->sync([$zc157->id]);

        return compact('owner', 'uploader', 'plugin', 'oldRelease', 'latestRelease', 'zc157', 'zc158', 'zc200', 'inactiveZc');
    }

    public function test_latest_release_uploader_can_replace_compatible_zc_versions(): void
    {
        ['uploader' => $uploader, 'plugin' => $plugin, 'latestRelease' => $latestRelease, 'zc158' => $zc158, 'zc200' => $zc200] = $this->makePlugin();

        $this->actingAs($uploader)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
                'zc_versions' => [$zc158->id, $zc200->id],
            ])
            ->assertRedirect(route('plugins.show', $plugin))
            ->assertSessionHas('plugin_success', __('plugin.flash.release_zc_versions_updated'));

        $this->assertEqualsCanonicalizing(
            ['1.5.8', '2.0.0'],
            $latestRelease->fresh()->zcVersions->pluck('version')->all(),
        );
    }

    public function test_plugin_owner_can_edit_latest_release_compatibility(): void
    {
        ['owner' => $owner, 'plugin' => $plugin, 'latestRelease' => $latestRelease, 'zc158' => $zc158] = $this->makePlugin();

        $this->actingAs($owner)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
                'zc_versions' => [$zc158->id],
            ])
            ->assertRedirect(route('plugins.show', $plugin));

        $this->assertSame(['1.5.8'], $latestRelease->fresh()->zcVersions->pluck('version')->all());
    }

    public function test_compatibility_can_be_cleared_entirely(): void
    {
        ['owner' => $owner, 'plugin' => $plugin, 'latestRelease' => $latestRelease] = $this->makePlugin();

        $this->actingAs($owner)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [])
            ->assertRedirect(route('plugins.show', $plugin));

        $this->assertCount(0, $latestRelease->fresh()->zcVersions);
    }

    public function test_editing_a_non_latest_release_is_forbidden(): void
    {
        ['uploader' => $uploader, 'plugin' => $plugin, 'oldRelease' => $oldRelease, 'zc158' => $zc158] = $this->makePlugin();

        $this->actingAs($uploader)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $oldRelease]), [
                'zc_versions' => [$zc158->id],
            ])
            ->assertForbidden();

        $this->assertSame(['1.5.7'], $oldRelease->fresh()->zcVersions->pluck('version')->all());
    }

    public function test_unrelated_user_cannot_edit_compatibility(): void
    {
        ['plugin' => $plugin, 'latestRelease' => $latestRelease, 'zc158' => $zc158] = $this->makePlugin();

        $this->actingAs(User::factory()->create())
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
                'zc_versions' => [$zc158->id],
            ])
            ->assertForbidden();

        $this->assertSame(['1.5.7'], $latestRelease->fresh()->zcVersions->pluck('version')->all());
    }

    public function test_guest_is_redirected(): void
    {
        ['plugin' => $plugin, 'latestRelease' => $latestRelease, 'zc158' => $zc158] = $this->makePlugin();

        $this->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
            'zc_versions' => [$zc158->id],
        ])->assertRedirect();

        $this->assertSame(['1.5.7'], $latestRelease->fresh()->zcVersions->pluck('version')->all());
    }

    public function test_inactive_zc_version_cannot_be_attached(): void
    {
        ['owner' => $owner, 'plugin' => $plugin, 'latestRelease' => $latestRelease, 'inactiveZc' => $inactiveZc] = $this->makePlugin();

        $this->actingAs($owner)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
                'zc_versions' => [$inactiveZc->id],
            ])
            ->assertSessionHasErrors('zc_versions.0');

        $this->assertSame(['1.5.7'], $latestRelease->fresh()->zcVersions->pluck('version')->all());
    }

    public function test_moderator_can_edit_compatibility(): void
    {
        ['plugin' => $plugin, 'latestRelease' => $latestRelease, 'zc158' => $zc158] = $this->makePlugin();

        $moderator = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $moderator->id,
            'entitlement_key' => 'operator.plugin.moderate',
            'grant_type' => 'manual',
            'granted_by_id' => $moderator->id,
        ]);

        $this->actingAs($moderator)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
                'zc_versions' => [$zc158->id],
            ])
            ->assertRedirect(route('plugins.show', $plugin));

        $this->assertSame(['1.5.8'], $latestRelease->fresh()->zcVersions->pluck('version')->all());
    }

    public function test_change_is_recorded_as_a_custom_audit_on_the_release(): void
    {
        $this->markTestSkipped('Audit entries are deferred by decision (audit logs are not carried yet).');

        ['uploader' => $uploader, 'plugin' => $plugin, 'latestRelease' => $latestRelease, 'zc158' => $zc158] = $this->makePlugin();

        $this->actingAs($uploader)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
                'zc_versions' => [$zc158->id],
            ])
            ->assertRedirect();

        $audit = Audit::query()
            ->where('auditable_type', $latestRelease->getMorphClass())
            ->where('auditable_id', $latestRelease->id)
            ->where('event', 'zcVersionsUpdated')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($uploader->id, (int) $audit->user_id);
        $this->assertSame(['1.5.7'], $audit->old_values['zc_versions']);
        $this->assertSame(['1.5.8'], $audit->new_values['zc_versions']);
    }

    public function test_no_audit_is_written_when_the_version_set_is_unchanged(): void
    {
        $this->markTestSkipped('Audit entries are deferred by decision (audit logs are not carried yet).');

        ['uploader' => $uploader, 'plugin' => $plugin, 'latestRelease' => $latestRelease, 'zc157' => $zc157] = $this->makePlugin();

        $this->actingAs($uploader)
            ->post(route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $latestRelease]), [
                'zc_versions' => [$zc157->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            0,
            Audit::query()
                ->where('auditable_type', $latestRelease->getMorphClass())
                ->where('event', 'zcVersionsUpdated')
                ->count(),
        );
    }

    public function test_edit_form_visibility_on_the_plugin_page(): void
    {
        ['uploader' => $uploader, 'plugin' => $plugin] = $this->makePlugin();

        $this->actingAs($uploader)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('Edit compatible Zen Cart versions');

        $this->actingAs(User::factory()->create())
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('Edit compatible Zen Cart versions');
    }
}
