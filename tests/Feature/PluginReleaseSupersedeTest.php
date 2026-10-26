<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginModeratorNote;
use Filagora\Plugins\Models\PluginType;
use Filagora\Plugins\Notifications\PluginNotification;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;
use ZipArchive;

class PluginReleaseSupersedeTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_release_form_warns_about_releases_awaiting_review(): void
    {
        [$plugin, $owner] = $this->activePlugin();
        $this->release($plugin, $owner, '1.0.0', PluginStatus::ACTIVE);
        $this->release($plugin, $owner, '1.1.0', PluginStatus::MODERATED);

        $this->actingAs($owner)
            ->get(route('plugins.release.new', $plugin))
            ->assertOk()
            ->assertSeeText(trans_choice('plugin.release_supersede.warning_title', 1, ['versions' => 'v1.1.0']))
            ->assertSee('name="supersede_pending_releases"', false);
    }

    public function test_release_form_has_no_warning_when_nothing_is_awaiting_review(): void
    {
        [$plugin, $owner] = $this->activePlugin();
        $this->release($plugin, $owner, '1.0.0', PluginStatus::ACTIVE);

        $this->actingAs($owner)
            ->get(route('plugins.release.new', $plugin))
            ->assertOk()
            ->assertDontSee('name="supersede_pending_releases"', false);
    }

    public function test_upload_is_refused_until_the_author_confirms_replacing_pending_releases(): void
    {
        Storage::fake('public');
        Notification::fake();
        [$plugin, $owner] = $this->activePlugin();
        $pending = $this->release($plugin, $owner, '1.1.0', PluginStatus::MODERATED);

        $this->actingAs($owner)
            ->post(route('plugins.release.store', $plugin), $this->releasePayload('1.2.0'))
            ->assertSessionHasErrors('supersede_pending_releases');

        $this->assertSame(PluginStatus::MODERATED, $pending->refresh()->status);
        $this->assertDatabaseMissing('plugin_releases', ['plugin_id' => $plugin->id, 'version' => 'v1.2.0']);
    }

    public function test_confirmed_upload_rejects_every_pending_release_permanently(): void
    {
        Storage::fake('public');
        Notification::fake();
        [$plugin, $owner] = $this->activePlugin();
        $live = $this->release($plugin, $owner, '1.0.0', PluginStatus::ACTIVE);
        $firstPending = $this->release($plugin, $owner, '1.1.0', PluginStatus::MODERATED);
        $secondPending = $this->release($plugin, $owner, '1.1.1', PluginStatus::MODERATED);

        $this->actingAs($owner)
            ->post(route('plugins.release.store', $plugin), $this->releasePayload('1.2.0') + ['supersede_pending_releases' => '1'])
            ->assertRedirect(route('plugins.show', $plugin))
            ->assertSessionHasNoErrors();

        $this->assertSame(PluginStatus::REJECTED_FINAL, $firstPending->refresh()->status);
        $this->assertSame(PluginStatus::REJECTED_FINAL, $secondPending->refresh()->status);
        $this->assertSame(PluginStatus::ACTIVE, $live->refresh()->status);
        $this->assertSame(
            PluginStatus::MODERATED,
            PluginRelease::query()->where('plugin_id', $plugin->id)->where('version', 'v1.2.0')->firstOrFail()->status,
        );
        $this->assertSame(2, PluginModeratorNote::query()->where('plugin_id', $plugin->id)->where('moderator_id', $owner->id)->count());
        Notification::assertNotSentTo($owner, PluginNotification::class, fn (PluginNotification $notification): bool => $notification->kind === 'release_rejected');
    }

    public function test_another_uploader_is_told_when_their_pending_release_is_superseded(): void
    {
        Storage::fake('public');
        Notification::fake();
        [$plugin, $owner] = $this->activePlugin();
        $coAuthor = User::factory()->create();
        $pending = $this->release($plugin, $coAuthor, '1.1.0', PluginStatus::MODERATED);

        $this->actingAs($owner)
            ->post(route('plugins.release.store', $plugin), $this->releasePayload('1.2.0') + ['supersede_pending_releases' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PluginStatus::REJECTED_FINAL, $pending->refresh()->status);
        Notification::assertSentTo($coAuthor, PluginNotification::class, fn (PluginNotification $notification): bool => $notification->kind === 'release_rejected');
    }

    public function test_api_upload_needs_explicit_opt_in_to_supersede_pending_releases(): void
    {
        $this->markTestSkipped('Needs the JSON API and account API tokens: slices S4d and S5');

        Storage::fake('public');
        Notification::fake();
        [$plugin, $owner] = $this->activePlugin();
        $pending = $this->release($plugin, $owner, '1.1.0', PluginStatus::MODERATED);
        $this->grantEntitlements($owner, ['api.plugins.write']);
        $token = $owner->createToken('plugin-api', ['plugins:write'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/plugins/'.$plugin->slug.'/releases', $this->releasePayload('1.2.0'), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supersede_pending_releases');

        $this->assertSame(PluginStatus::MODERATED, $pending->refresh()->status);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/plugins/'.$plugin->slug.'/releases', $this->releasePayload('1.2.0') + ['supersede_pending_releases' => '1'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.version', 'v1.2.0')
            ->assertJsonPath('meta.superseded_versions', ['1.1.0']);

        $this->assertSame(PluginStatus::REJECTED_FINAL, $pending->refresh()->status);
    }

    /**
     * @return array{0: Plugin, 1: User}
     */
    private function activePlugin(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $type = PluginType::query()->create([
            'name' => 'Supersede Type',
            'slug' => 'supersede-type',
            'is_active' => true,
            'position' => 10,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Supersede Plugin',
            'slug' => 'supersede-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        return [$plugin, $owner];
    }

    private function release(Plugin $plugin, User $uploader, string $version, PluginStatus $status): PluginRelease
    {
        return PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => 'v'.$version,
            'zip_path' => 'plugins/supersede-'.$version.'.zip',
            'zip_filename' => 'supersede-'.$version.'.zip',
            'status' => $status->value,
            'encapsulated' => 'unknown',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function releasePayload(string $version): array
    {
        return [
            'version' => $version,
            'zip_file' => UploadedFile::fake()->createWithContent('supersede-plugin.zip', $this->makeZipWithRoot('supersede_plugin')),
            'encapsulated' => 'unknown',
        ];
    }

    private function makeZipWithRoot(string $rootDirectory): string
    {
        $path = tempnam(sys_get_temp_dir(), 'plugin-supersede-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addEmptyDir($rootDirectory);
        $zip->addFromString($rootDirectory.'/manifest.json', '{}');
        $zip->close();

        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }
}
