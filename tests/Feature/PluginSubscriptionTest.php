<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PluginMailNotification;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginSubscription;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginSubscriptionTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('audit.enabled', true);
        config()->set('audit.console', true);
    }

    public function test_authenticated_user_can_subscribe_and_manage_plugin_subscription_from_profile(): void
    {
        [$plugin, $owner] = $this->createPlugin();
        $subscriber = User::factory()->create();

        $this->actingAs($subscriber)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('Subscribe to updates');

        $this->actingAs($subscriber)
            ->withHeader('User-Agent', 'PluginSubscriptionTest/1.0')
            ->post(route('plugins.subscribe', $plugin), [], ['REMOTE_ADDR' => '203.0.113.20'])
            ->assertRedirect(route('plugins.show', $plugin));

        $this->assertDatabaseHas('plugin_subscriptions', [
            'plugin_id' => $plugin->id,
            'user_id' => $subscriber->id,
        ]);

        $this->actingAs($subscriber)
            ->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk()
            ->assertSee('Plugin Subscriptions')
            ->assertSee($plugin->name);

        $this->actingAs($subscriber)
            ->withHeader('User-Agent', 'PluginSubscriptionTest/1.0')
            ->post(route('profile.plugin-subscriptions.remove', $plugin), [], ['REMOTE_ADDR' => '203.0.113.20'])
            ->assertRedirect(route('profile.show', ['tab' => 'subscriptions']));

        $this->assertDatabaseMissing('plugin_subscriptions', [
            'plugin_id' => $plugin->id,
            'user_id' => $subscriber->id,
        ]);

        $this->assertSame($owner->id, $plugin->owner_id);
    }

    public function test_guest_plugin_subscribe_redirects_to_login_modal(): void
    {
        [$plugin] = $this->createPlugin();

        $this->post(route('plugins.subscribe', $plugin))
            ->assertRedirect('/?auth=login&intended=%2Fplugins%2F'.$plugin->slug);
    }

    public function test_unverified_user_does_not_see_plugin_subscription_actions(): void
    {
        [$plugin] = $this->createPlugin();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('Subscribe to updates')
            ->assertDontSee('Unsubscribe from updates');
    }

    public function test_auto_published_release_notifies_plugin_subscribers_except_uploader(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$plugin, $uploader] = $this->createPlugin();
        $subscriber = User::factory()->create();
        $this->makeForumModerator($uploader);
        $this->grantCanonicalOperatorEntitlements($uploader, 'plugin_auto_publish');
        $version = ZcVersion::query()->create([
            'version' => '2.1.0',
            'is_active' => true,
            'position' => 20,
        ]);

        PluginSubscription::query()->create([
            'plugin_id' => $plugin->id,
            'user_id' => $subscriber->id,
        ]);

        $this->actingAs($uploader)
            ->post(route('plugins.release.store', $plugin), [
                'version' => '1.1.0',
                'zip_file' => $this->validZip('plugin-update.zip'),
                'changelog' => 'New features',
                'encapsulated' => 'unknown',
                'zc_versions' => [$version->id],
            ])
            ->assertRedirect(route('plugins.show', $plugin));

        Notification::assertSentTo($subscriber, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_updated');
        Notification::assertNotSentTo($uploader, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_updated');
    }

    public function test_approved_release_notifies_plugin_subscribers_except_release_author(): void
    {
        Notification::fake();

        [$plugin] = $this->createPlugin();
        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $subscriber = User::factory()->create();
        $uploader = User::factory()->create();

        PluginSubscription::query()->create([
            'plugin_id' => $plugin->id,
            'user_id' => $subscriber->id,
        ]);

        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => '1.2.0',
            'internal_version' => 2,
            'zip_path' => 'plugins/pending-update.zip',
            'zip_filename' => 'pending-update.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
                'note' => 'Looks good.',
            ])
            ->assertRedirect();

        Notification::assertSentTo($subscriber, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_updated');
        Notification::assertSentTo($uploader, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_approved');
        Notification::assertNotSentTo($uploader, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_updated');
    }

    public function test_profile_plugin_subscriptions_are_ordered_by_most_recent_subscription_first_when_timestamps_match(): void
    {
        $subscriber = User::factory()->create();
        $owner = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools',
            'is_active' => true,
            'position' => 30,
        ]);
        $olderPlugin = Plugin::query()->create([
            'name' => 'Older Subscription Plugin',
            'slug' => 'older-subscription-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $newerPlugin = Plugin::query()->create([
            'name' => 'Newer Subscription Plugin',
            'slug' => 'newer-subscription-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $subscriber->subscribedPlugins()->syncWithoutDetaching([$olderPlugin->id]);
        $subscriber->subscribedPlugins()->syncWithoutDetaching([$newerPlugin->id]);

        $sharedTimestamp = now()->subMinute();
        DB::table('plugin_subscriptions')
            ->where('user_id', $subscriber->id)
            ->update([
                'created_at' => $sharedTimestamp,
                'updated_at' => $sharedTimestamp,
            ]);

        $response = $this->actingAs($subscriber)
            ->get(route('profile.show', ['tab' => 'subscriptions']))
            ->assertOk();

        $content = $response->getContent();

        $this->assertNotFalse($content);
        $this->assertLessThan(
            strpos($content, $olderPlugin->name),
            strpos($content, $newerPlugin->name)
        );
    }

    /**
     * @return array{0: Plugin, 1: User}
     */
    private function createPlugin(): array
    {
        $owner = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools',
            'is_active' => true,
            'position' => 30,
        ]);
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

        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/missing.zip',
            'zip_filename' => 'missing.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);

        $release->zcVersions()->sync([$version->id]);

        return [$plugin, $owner];
    }

    private function validZip(string $filename): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'plugin-sub-').'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('plugin.php', "<?php\nreturn true;\n");
        $zip->close();

        return new UploadedFile($path, $filename, 'application/zip', null, true);
    }
}
