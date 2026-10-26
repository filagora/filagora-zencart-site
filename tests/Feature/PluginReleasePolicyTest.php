<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\PluginReleasePolicy;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginReleasePolicyTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    /**
     * Filament falls back to *allowing* an action when a policy exists but
     * is missing the specific ability method it checks (non-strict mode) —
     * PluginReleasePolicy previously had only moderate()/moderateAny(), so
     * any authenticated user could edit or bulk-delete plugin releases via
     * the dashboard. This exercises the actual Filament entry point, not
     * just the raw policy method, since that's where the gap manifested.
     */
    private function createPluginReleaseRecord(): PluginRelease
    {
        $owner = User::factory()->create();
        $uploader = User::factory()->create();

        $type = PluginType::query()->create([
            'name' => 'Policy Release Type',
            'slug' => 'policy-release-type-'.str()->lower(str()->random(8)),
            'is_active' => true,
            'position' => 100,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Policy Release Plugin',
            'slug' => 'policy-release-plugin-'.str()->lower(str()->random(8)),
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        return PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/policy-release.zip',
            'zip_filename' => 'policy-release.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);
    }
}
