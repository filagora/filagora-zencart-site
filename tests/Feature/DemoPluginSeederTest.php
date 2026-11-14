<?php

namespace Tests\Feature;

use Database\Seeders\DemoPluginSeeder;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoPluginSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_plugins_with_releases_and_archives_and_is_idempotent(): void
    {
        Storage::fake(config('filagora-plugins.storage.disk'));

        $this->seed(DemoPluginSeeder::class);
        $plugins = Plugin::query()->count();
        $releases = PluginRelease::query()->count();

        $this->assertGreaterThan(0, $plugins);
        $this->assertSame($plugins - 1, Plugin::query()->where('status', PluginStatus::ACTIVE)->count());

        $release = PluginRelease::query()->with('zcVersions')->firstOrFail();
        $this->assertTrue(Storage::disk(config('filagora-plugins.storage.disk'))->exists($release->zip_path));
        $this->assertNotNull($release->checksum_sha256);
        $this->assertNotEmpty($release->zcVersions);

        $this->seed(DemoPluginSeeder::class);
        $this->assertSame($plugins, Plugin::query()->count());
        $this->assertSame($releases, PluginRelease::query()->count());
    }
}
