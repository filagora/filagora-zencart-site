<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\PluginsSecurity\Enums\ScanTrigger as SecurityScanTrigger;
use Filagora\PluginsSecurity\Jobs\ScanReleaseJob;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;
use ZipArchive;

class PluginReleaseScanDispatchTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('filagora-plugins-security.scanner.dispatch_on_upload', true);
    }

    private function zipUpload(array $entries, string $filename = 'plugin.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'scan-dispatch-').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return new UploadedFile($path, $filename, 'application/zip', null, true);
    }

    public function test_new_plugin_submission_queues_a_security_scan_for_the_first_release(): void
    {
        Bus::fake([ScanReleaseJob::class]);

        $user = User::factory()->create();
        $type = PluginType::query()->create(['name' => 'Layout', 'slug' => 'layout', 'is_active' => true]);

        $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Scanned Plugin',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_encapsulated' => 'unknown',
            'release_zip_file' => $this->zipUpload(['my-plugin/index.php' => "<?php return true;\n"]),
        ])->assertRedirect();

        $release = PluginRelease::query()->firstOrFail();

        Bus::assertDispatched(
            ScanReleaseJob::class,
            fn (ScanReleaseJob $job): bool => $job->releaseId === $release->id
                && $job->trigger === SecurityScanTrigger::UPLOAD
                && $job->triggeredById === $user->id,
        );
    }

    public function test_adding_a_release_to_an_existing_plugin_queues_a_scan(): void
    {
        Bus::fake([ScanReleaseJob::class]);

        $owner = User::factory()->create();
        $type = PluginType::query()->create(['name' => 'Layout', 'slug' => 'layout', 'is_active' => true]);
        $plugin = Plugin::query()->create([
            'name' => 'Existing',
            'slug' => 'existing',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($owner)->post(route('plugins.release.store', $plugin), [
            'version' => '2.0.0',
            'encapsulated' => 'unknown',
            'zip_file' => $this->zipUpload(['existing/x.php' => "<?php return 1;\n"]),
        ])->assertRedirect();

        $release = $plugin->releases()->where('version', 'v2.0.0')->firstOrFail();

        Bus::assertDispatched(
            ScanReleaseJob::class,
            fn (ScanReleaseJob $job): bool => $job->releaseId === $release->id,
        );
    }
}
