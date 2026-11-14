<?php

namespace Database\Seeders;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Enums\Encapsulated;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * A made-up plugin library for a fresh local install: plugin types, Zen Cart versions, and a few
 * plugins with releases whose archives are small real zips, so the library, the plugin pages and
 * downloads all have something to show. Everything is invented. Local and test databases only.
 */
class DemoPluginSeeder extends Seeder
{
    private const TYPES = [
        'Payment Modules',
        'Shipping Modules',
        'Order Total Modules',
        'Templates',
        'Admin Tools',
        'Other',
    ];

    private const ZC_VERSIONS = ['v1.5.7', 'v1.5.8', 'v2.0.0', 'v2.1.0', 'v2.2.0'];

    /**
     * @var list<array{name: string, type: string, description: string, releases: list<array{version: string, changelog: string, zc: list<string>, status?: PluginStatus}>, status?: PluginStatus}>
     */
    private const PLUGINS = [
        [
            'name' => 'Example Payment Gateway',
            'type' => 'Payment Modules',
            'description' => 'Accept card payments through a hosted checkout page.',
            'releases' => [
                ['version' => '1.0.0', 'changelog' => 'Initial release.', 'zc' => ['v1.5.8']],
                ['version' => '1.1.0', 'changelog' => 'Adds refunds from the admin order screen.', 'zc' => ['v1.5.8', 'v2.0.0']],
                ['version' => '2.0.0', 'changelog' => 'Encapsulated for Zen Cart 2.x.', 'zc' => ['v2.0.0', 'v2.1.0']],
            ],
        ],
        [
            'name' => 'Table Rate Plus',
            'type' => 'Shipping Modules',
            'description' => 'Shipping rates by weight, price or item count, with per-zone tables.',
            'releases' => [
                ['version' => '3.2.1', 'changelog' => 'Fixes rounding on weight bands.', 'zc' => ['v1.5.7', 'v1.5.8']],
            ],
        ],
        [
            'name' => 'Loyalty Discount',
            'type' => 'Order Total Modules',
            'description' => 'Give returning customers a percentage off once they pass a spend threshold.',
            'releases' => [
                ['version' => '0.9.0', 'changelog' => 'First public beta.', 'zc' => ['v2.0.0']],
                ['version' => '1.0.0', 'changelog' => 'Stable release.', 'zc' => ['v2.0.0', 'v2.1.0']],
            ],
        ],
        [
            'name' => 'Modern Storefront Template',
            'type' => 'Templates',
            'description' => 'A responsive storefront template with a sticky header and a mega menu.',
            'releases' => [
                ['version' => '1.4.0', 'changelog' => 'Dark mode and accessibility fixes.', 'zc' => ['v2.1.0', 'v2.2.0']],
            ],
        ],
        [
            'name' => 'Admin Activity Digest',
            'type' => 'Admin Tools',
            'description' => 'Emails a daily summary of admin logins and configuration changes.',
            'releases' => [
                ['version' => '1.0.2', 'changelog' => 'Fixes the digest time zone.', 'zc' => ['v1.5.8', 'v2.0.0', 'v2.1.0']],
            ],
        ],
        [
            'name' => 'Pending Review Example',
            'type' => 'Other',
            'description' => 'Waiting in the moderation queue, so the review screens have a row.',
            'status' => PluginStatus::MODERATED,
            'releases' => [
                ['version' => '0.1.0', 'changelog' => 'Awaiting review.', 'zc' => ['v2.1.0'], 'status' => PluginStatus::MODERATED],
            ],
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DemoPluginSeeder only runs in a local or test environment.');
        }

        $types = collect(self::TYPES)->mapWithKeys(fn (string $name, int $index): array => [
            $name => PluginType::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'position' => ($index + 1) * 10],
            ),
        ]);

        $zcVersions = collect(self::ZC_VERSIONS)->mapWithKeys(fn (string $version, int $index): array => [
            $version => ZcVersion::query()->firstOrCreate(
                ['version' => $version],
                ['is_active' => true, 'position' => ($index + 1) * 10],
            ),
        ]);

        $author = User::query()->firstOrCreate(
            ['email' => 'plugin-author@example.test'],
            ['name' => 'Plugin Author', 'username' => 'plugin-author', 'password' => 'password', 'email_verified_at' => now()],
        );

        foreach (self::PLUGINS as $definition) {
            $slug = Str::slug($definition['name']);

            // A plugin that is already there keeps its releases; re-running adds nothing.
            $plugin = Plugin::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'plugin_type_id' => $types[$definition['type']]->id,
                    'description' => $definition['description'],
                    'github_repository_url' => 'example/'.$slug,
                    'owner_id' => $author->id,
                    'status' => $definition['status'] ?? PluginStatus::ACTIVE,
                ],
            );

            if (! $plugin->wasRecentlyCreated) {
                continue;
            }

            foreach ($definition['releases'] as $index => $release) {
                $path = $this->storeArchive($slug, $release['version']);
                $publishedAt = now()->subDays((count($definition['releases']) - $index) * 30);

                $model = PluginRelease::query()->create([
                    'plugin_id' => $plugin->id,
                    'uploaded_by_id' => $author->id,
                    'version' => $release['version'],
                    'zip_path' => $path,
                    'changelog' => $release['changelog'],
                    'download_count' => random_int(10, 2000),
                    'encapsulated' => Encapsulated::YES,
                    'status' => $release['status'] ?? PluginStatus::ACTIVE,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ]);

                $model->zcVersions()->sync($zcVersions->only($release['zc'])->pluck('id')->all());
            }
        }
    }

    /**
     * Writes a minimal archive to the plugins disk and answers its path on that disk.
     */
    private function storeArchive(string $slug, string $version): string
    {
        $path = "plugins/{$slug}-{$version}.zip";
        $disk = Storage::disk((string) config('filagora-plugins.storage.disk'));
        $temp = tempnam(sys_get_temp_dir(), 'demo-plugin');

        $zip = new ZipArchive;
        $zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString("{$slug}/README.txt", "Demo archive for {$slug} {$version}. Not a working plugin.\n");
        $zip->close();

        $disk->put($path, (string) file_get_contents($temp));
        @unlink($temp);

        return $path;
    }
}
