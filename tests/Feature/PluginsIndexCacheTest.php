<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PluginsIndexCache;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginsIndexCacheTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_plugins_warm_cache_command_populates_index_cache_entry(): void
    {
        $this->seedActivePluginLibrary();

        PluginsIndexCache::resetVersion();
        $cacheKey = PluginsIndexCache::keyFor(null, 1, 'date_desc');
        $this->assertNull(Cache::get($cacheKey));

        $exit = Artisan::call('plugins:warm-cache', ['--flush' => true, '--max-pages' => 1]);
        $this->assertSame(0, $exit);

        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_plugins_warm_cache_command_bypasses_frontend_maintenance_mode(): void
    {
        $this->seedActivePluginLibrary();

        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.allow_during_tests', true);

        PluginsIndexCache::resetVersion();
        $cacheKey = PluginsIndexCache::keyFor(null, 1, 'date_desc');

        $exit = Artisan::call('plugins:warm-cache', ['--flush' => true, '--max-pages' => 1]);

        $this->assertSame(0, $exit);
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_plugins_warm_cache_command_restores_frontend_maintenance_flag_after_run(): void
    {
        $this->seedActivePluginLibrary();

        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.allow_during_tests', true);

        $exit = Artisan::call('plugins:warm-cache', ['--flush' => true, '--max-pages' => 1]);

        $this->assertSame(0, $exit);
        $this->assertTrue(config('app.frontend_maintenance.enabled'));
    }

    public function test_plugins_index_cache_version_bumps_when_plugin_changes(): void
    {
        [$plugin] = $this->seedActivePluginLibrary();

        PluginsIndexCache::resetVersion();
        $this->get('/plugins')->assertOk();
        $beforeVersion = PluginsIndexCache::currentVersion();

        $plugin->name = 'Updated Name';
        $plugin->save();

        $afterVersion = PluginsIndexCache::currentVersion();
        $this->assertGreaterThan($beforeVersion, $afterVersion);

        $newKey = PluginsIndexCache::keyFor(null, 1, 'date_desc');
        $this->assertNull(Cache::get($newKey));

        $this->get('/plugins')->assertOk();
        $cached = Cache::get($newKey);
        $this->assertIsArray($cached);
        $this->assertIsArray($cached['typeRows'] ?? null);
        $this->assertIsArray($cached['latestPlugins'] ?? null);
    }

    /**
     * @return array{0: Plugin, 1: PluginType}
     */
    private function seedActivePluginLibrary(): array
    {
        $owner = User::factory()->create();

        $type = PluginType::query()->create([
            'name' => 'Cache Warm Type',
            'slug' => 'cache-warm-type',
            'description' => 'Test type',
            'is_active' => true,
            'position' => 10,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Cache Warm Plugin',
            'slug' => 'cache-warm-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/cache-warm.zip',
            'zip_filename' => 'cache-warm.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);

        return [$plugin, $type];
    }
}
