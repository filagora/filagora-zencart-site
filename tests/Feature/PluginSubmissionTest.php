<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Enums\ReleaseRefusal;
use Filagora\Plugins\Models\PluginType;
use Filagora\Plugins\Notifications\PluginNotification;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\Thread;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;
use ZipArchive;

class PluginSubmissionTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_authenticated_user_can_submit_plugin_with_initial_release(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Payment Modules',
            'slug' => 'payment-modules',
            'is_active' => true,
            'position' => 10,
        ]);
        $zcVersion = ZcVersion::query()->create([
            'version' => '2.0.0',
            'is_active' => true,
            'position' => 10,
        ]);
        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Stripe Gateway',
            'plugin_type_id' => $type->id,
            'description' => 'Stripe integration',
            'github_repository_url' => 'https://github.com/example/stripe',
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('stripe-gateway.zip'),
            'release_changelog' => 'Initial release',
            'release_encapsulated' => 'unknown',
            'release_zc_versions' => [$zcVersion->id],
        ]);

        $response->assertRedirect(route('plugins.index'));
        $response->assertSessionHas('plugin_success');

        $plugin = Plugin::query()->where('name', 'Stripe Gateway')->firstOrFail();
        $release = PluginRelease::query()->where('plugin_id', $plugin->id)->firstOrFail();

        $this->assertSame($user->id, $plugin->owner_id);
        $this->assertNull($plugin->legacy_vbulletin_file_id);
        $this->assertNull($plugin->zip_root);
        $this->assertSame(PluginStatus::MODERATED, $plugin->status);
        $this->assertSame('v1.0.0', $release->version);
        $this->assertSame($user->id, $release->uploaded_by_id);
        $this->assertSame(PluginStatus::MODERATED, $release->status);
        $this->assertSame('unknown', $release->encapsulated->value);
        $this->assertDatabaseHas('plugin_release_zc_versions', [
            'plugin_release_id' => $release->id,
            'plugin_zc_version_id' => $zcVersion->id,
        ]);
        Notification::assertSentTo($user, PluginNotification::class);
        Notification::assertSentOnDemand(PluginNotification::class, fn (PluginNotification $notification, array $channels, object $notifiable): bool => $notification->kind === 'submitted_support' && ($notifiable->routes['mail'] ?? null) === 'plugin_support@zen-cart.com');
        Storage::disk('public')->assertExists($release->zip_path);
    }

    public function test_creating_a_plugin_does_not_crash_when_its_id_collides_with_an_existing_legacy_vbulletin_file_id(): void
    {
        $this->markTestSkipped('Needs filagora-zencart 0.5.1 (library id collision guard)');

        // plugins.id is a plain auto-increment counter unrelated to
        // historical vBulletin file ids stored in legacy_vbulletin_file_id,
        // so a brand-new plugin's id can coincidentally match a number an
        // already-imported plugin holds there. That must never crash, and the
        // new plugin must not share a public ID with the imported one (the
        // version-check lookup would answer for the wrong plugin), so it is
        // given an unused legacy_vbulletin_file_id instead.
        $type = PluginType::query()->create([
            'name' => 'Collision Type',
            'slug' => 'collision-type',
            'is_active' => true,
            'position' => 97,
        ]);

        $importedLegacyPlugin = Plugin::query()->create([
            'name' => 'Imported Legacy Plugin',
            'slug' => 'imported-legacy-plugin',
            'plugin_type_id' => $type->id,
            'status' => PluginStatus::ACTIVE->value,
            'legacy_vbulletin_file_id' => 99999,
        ]);

        $collidingPlugin = Plugin::query()->forceCreate([
            'id' => 99999,
            'name' => 'New Plugin With Colliding Id',
            'slug' => 'new-plugin-with-colliding-id',
            'plugin_type_id' => $type->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        $collidingPlugin->refresh();
        $this->assertSame(100000, $collidingPlugin->legacy_vbulletin_file_id);
        $this->assertSame(100000, $collidingPlugin->canonicalId());
        $this->assertSame(99999, (int) $importedLegacyPlugin->fresh()->legacy_vbulletin_file_id);
    }

    public function test_plugin_auto_publish_entitlement_makes_initial_submission_live_immediately(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'operator.plugin.auto_publish',
            'grant_type' => 'manual',
            'granted_by_id' => $user->id,
        ]);

        $type = PluginType::query()->create([
            'name' => 'Trusted Payment Modules',
            'slug' => 'trusted-payment-modules',
            'is_active' => true,
            'position' => 15,
        ]);
        $zcVersion = ZcVersion::query()->create([
            'version' => '2.0.1',
            'is_active' => true,
            'position' => 15,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Trusted Stripe Gateway',
            'plugin_type_id' => $type->id,
            'description' => 'Trusted Stripe integration',
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('trusted-stripe-gateway.zip'),
            'release_changelog' => 'Initial release',
            'release_encapsulated' => 'unknown',
            'release_zc_versions' => [$zcVersion->id],
        ]);

        $response->assertRedirect(route('plugins.index'));
        $response->assertSessionHas('plugin_success', __('plugin.flash.initial_release_live'));

        $plugin = Plugin::query()->where('name', 'Trusted Stripe Gateway')->firstOrFail();
        $release = PluginRelease::query()->where('plugin_id', $plugin->id)->firstOrFail();

        $this->assertSame(PluginStatus::ACTIVE, $plugin->status);
        $this->assertSame(PluginStatus::ACTIVE, $release->status);

        Notification::assertSentTo($user, PluginNotification::class, fn (PluginNotification $notification): bool => $notification->kind === 'submitted_live');
        Notification::assertSentOnDemand(PluginNotification::class, fn (PluginNotification $notification, array $channels, object $notifiable): bool => $notification->kind === 'submitted_support' && ($notifiable->routes['mail'] ?? null) === 'plugin_support@zen-cart.com');
    }

    public function test_auto_published_submission_updates_the_manifest_plugin_id_to_the_real_id(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'operator.plugin.auto_publish',
            'grant_type' => 'manual',
            'granted_by_id' => $user->id,
        ]);

        $type = PluginType::query()->create([
            'name' => 'Manifest Update Type',
            'slug' => 'manifest-update-type',
            'is_active' => true,
            'position' => 16,
        ]);

        $manifestEntry = 'zc_plugins/ManifestWidget/v1.0.0/manifest.php';

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Manifest Widget',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeManifestUploadedFile($manifestEntry, 0),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.index'));

        $plugin = Plugin::query()->where('name', 'Manifest Widget')->firstOrFail();
        $release = PluginRelease::query()->where('plugin_id', $plugin->id)->firstOrFail();

        $this->assertSame(PluginStatus::ACTIVE, $plugin->status);
        $this->assertStringContainsString(
            "'pluginId' => {$plugin->canonicalId()},",
            $this->readManifestFromStoredZip($release->zip_path, $manifestEntry)
        );
    }

    public function test_moderated_submission_leaves_the_manifest_plugin_id_untouched_until_approval(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Manifest Pending Type',
            'slug' => 'manifest-pending-type',
            'is_active' => true,
            'position' => 17,
        ]);

        $manifestEntry = 'zc_plugins/PendingWidget/v1.0.0/manifest.php';

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Pending Widget',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeManifestUploadedFile($manifestEntry, 0),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.index'));

        $plugin = Plugin::query()->where('name', 'Pending Widget')->firstOrFail();
        $release = PluginRelease::query()->where('plugin_id', $plugin->id)->firstOrFail();

        $this->assertSame(PluginStatus::MODERATED, $plugin->status);
        $this->assertStringContainsString(
            "'pluginId' => 0,",
            $this->readManifestFromStoredZip($release->zip_path, $manifestEntry)
        );
    }

    public function test_submitting_a_plugin_can_auto_create_a_support_thread(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Auto Thread Type',
            'slug' => 'auto-thread-type',
            'is_active' => true,
            'position' => 90,
        ]);
        $forumGroup = ForumGroup::query()->create([
            'name' => 'Community Contributed Add-Ons',
            'slug' => 'community-contributed-add-ons',
            'is_active' => true,
        ]);
        $supportForum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Addon Admin Tools',
            'slug' => 'addon-admin-tools',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => true,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Auto Thread Plugin',
            'plugin_type_id' => $type->id,
            'create_support_thread' => '1',
            'support_thread_forum_id' => $supportForum->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('auto-thread-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.index'));

        $plugin = Plugin::query()->where('name', 'Auto Thread Plugin')->firstOrFail();
        $thread = Thread::query()->where('forum_id', $supportForum->id)->where('title', 'Auto Thread Plugin')->first();

        $this->assertNotNull($thread);
        $this->assertSame($plugin->forum_thread_id, $thread->id);
        $this->assertSame($user->id, $thread->author_id);
        $this->assertDatabaseHas('posts', [
            'thread_id' => $thread->id,
            'author_id' => $user->id,
            'body' => __('plugin.support_thread.default_body', ['plugin' => 'Auto Thread Plugin']),
        ]);
    }

    public function test_creating_a_support_thread_uses_a_custom_title_when_the_author_provides_one(): void
    {
        Storage::fake('public');
        [$user, $type, $supportForum] = $this->pluginSubmitFixtures('custom-title');

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Custom Title Plugin',
            'plugin_type_id' => $type->id,
            'support_thread_mode' => 'create',
            'support_thread_forum_id' => $supportForum->id,
            'support_thread_title' => 'Support & help for the Custom Title Plugin',
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('custom-title-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.index'));

        $threadId = (int) Plugin::query()->where('name', 'Custom Title Plugin')->value('forum_thread_id');
        $this->assertSame(
            'Support & help for the Custom Title Plugin',
            Thread::query()->whereKey($threadId)->value('title'),
        );
    }

    public function test_creating_a_support_thread_falls_back_to_the_plugin_name_when_the_title_is_blank(): void
    {
        Storage::fake('public');
        [$user, $type, $supportForum] = $this->pluginSubmitFixtures('blank-title');

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Blank Title Plugin',
            'plugin_type_id' => $type->id,
            'support_thread_mode' => 'create',
            'support_thread_forum_id' => $supportForum->id,
            'support_thread_title' => '   ',
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('blank-title-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.index'));

        $threadId = (int) Plugin::query()->where('name', 'Blank Title Plugin')->value('forum_thread_id');
        $this->assertSame('Blank Title Plugin', Thread::query()->whereKey($threadId)->value('title'));
    }

    public function test_double_submitting_plugin_with_support_thread_does_not_create_duplicate_support_thread(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Resubmit Thread Type',
            'slug' => 'resubmit-thread-type',
            'is_active' => true,
            'position' => 90,
        ]);
        $forumGroup = ForumGroup::query()->create([
            'name' => 'Resubmit Add-Ons',
            'slug' => 'resubmit-add-ons',
            'is_active' => true,
        ]);
        $supportForum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Resubmit Admin Tools',
            'slug' => 'resubmit-admin-tools',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => true,
        ]);

        $payload = [
            'name' => 'Resubmitted Plugin',
            'plugin_type_id' => $type->id,
            'create_support_thread' => '1',
            'support_thread_forum_id' => $supportForum->id,
            'release_version' => '1.0.0',
            'release_encapsulated' => 'unknown',
        ];

        Carbon::setTestNow(now());

        $this->actingAs($user)->post(route('plugins.store'), $payload + [
            'release_zip_file' => $this->validZip('resubmitted-plugin.zip'),
        ])->assertRedirect(route('plugins.index'));

        // Simulate a double-click/back-button resubmit (e.g. a slow zip
        // upload) a few seconds later.
        $this->actingAs($user)->post(route('plugins.store'), $payload + [
            'release_zip_file' => $this->validZip('resubmitted-plugin-2.zip'),
        ])->assertRedirect(route('plugins.index'));

        Carbon::setTestNow();

        $this->assertSame(
            1,
            Thread::query()->where('forum_id', $supportForum->id)->where('title', 'Resubmitted Plugin')->count()
        );

        $plugins = Plugin::query()->where('name', 'Resubmitted Plugin')->get();
        $this->assertSame(2, $plugins->count());
        $this->assertSame(
            $plugins->first()->forum_thread_id,
            $plugins->last()->forum_thread_id,
        );

        // The auto-created support thread keeps notifying the plugin's author,
        // whatever their "subscribe when I post" setting is.
        $this->assertDatabaseHas('thread_subscriptions', [
            'thread_id' => $plugins->first()->forum_thread_id,
            'user_id' => $user->id,
        ]);
    }

    public function test_auto_created_support_thread_follows_the_forums_normal_moderation_rules(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Auto Thread Moderated Type',
            'slug' => 'auto-thread-moderated-type',
            'is_active' => true,
            'position' => 91,
        ]);
        $forumGroup = ForumGroup::query()->create([
            'name' => 'Community Contributed Add-Ons Moderated',
            'slug' => 'community-contributed-add-ons-moderated',
            'is_active' => true,
        ]);
        $supportForum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Addon Payment Modules',
            'slug' => 'addon-payment-modules',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => true,
            'requires_thread_approval' => true,
        ]);

        $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Moderated Thread Plugin',
            'plugin_type_id' => $type->id,
            'create_support_thread' => '1',
            'support_thread_forum_id' => $supportForum->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('moderated-thread-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ])->assertRedirect(route('plugins.index'));

        $thread = Thread::query()->where('forum_id', $supportForum->id)->where('title', 'Moderated Thread Plugin')->firstOrFail();

        $this->assertSame(ContentModerationStatus::PENDING, $thread->moderation_status);
    }

    public function test_support_thread_forum_id_must_be_an_eligible_plugin_support_forum(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Ineligible Forum Type',
            'slug' => 'ineligible-forum-type',
            'is_active' => true,
            'position' => 92,
        ]);
        $forumGroup = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-ineligible',
            'is_active' => true,
        ]);
        $ineligibleForum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Announcements',
            'slug' => 'announcements-ineligible',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => false,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Ineligible Forum Plugin',
            'plugin_type_id' => $type->id,
            'create_support_thread' => '1',
            'support_thread_forum_id' => $ineligibleForum->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('ineligible-forum-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.submit'));
        $response->assertSessionHasErrors(['support_thread_forum_id']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Ineligible Forum Plugin']);
    }

    public function test_create_support_thread_requires_a_forum_selection(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Missing Forum Type',
            'slug' => 'missing-forum-type',
            'is_active' => true,
            'position' => 93,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Missing Forum Plugin',
            'plugin_type_id' => $type->id,
            'create_support_thread' => '1',
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('missing-forum-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.submit'));
        $response->assertSessionHasErrors(['support_thread_forum_id']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Missing Forum Plugin']);
    }

    public function test_submit_page_lists_eligible_plugin_support_forums(): void
    {
        $user = User::factory()->create();
        $forumGroup = ForumGroup::query()->create([
            'name' => 'Community Contributed Add-Ons Listing',
            'slug' => 'community-contributed-add-ons-listing',
            'is_active' => true,
        ]);
        $eligibleForum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Addon Sideboxes',
            'slug' => 'addon-sideboxes-listing',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => true,
        ]);
        $ineligibleForum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Contribution-Writing Guidelines',
            'slug' => 'contribution-writing-guidelines-listing',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => false,
        ]);

        $response = $this->actingAs($user)->get(route('plugins.submit'));

        $response->assertOk();
        $response->assertSee('Create a new support thread');
        $response->assertSee($eligibleForum->name);
        $response->assertDontSee($ineligibleForum->name);
    }

    public function test_submit_page_explains_the_encapsulated_manifest_plugin_id_rewrite(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('plugins.submit'));

        $response->assertOk();
        $response->assertSee('<code>pluginId</code>', false);
        $response->assertSee('the first time a release is published');
    }

    public function test_submitting_a_new_plugin_no_longer_carries_a_lint_result_in_the_email(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Scan Queue Type',
            'slug' => 'scan-queue-type',
            'is_active' => true,
            'position' => 94,
        ]);

        $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Scan Queue Plugin',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeZipUploadedFileWithPhp([
                'includes/valid.php' => "<?php\necho 'ok';\n",
            ]),
            'release_encapsulated' => 'unknown',
        ])->assertRedirect(route('plugins.index'));

        $this->assertTrue(PluginRelease::query()->exists());

        // The submission emails still go out; they no longer carry an inline
        // lint result (the async scan result arrives later, only if there's a
        // problem). See PluginReleaseScanDispatchTest for the scan dispatch.
        Notification::assertSentTo($user, PluginNotification::class, fn (PluginNotification $notification): bool => $notification->kind === 'submitted');
    }

    public function test_broken_php_does_not_block_a_new_plugin_submission(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Broken PHP Type',
            'slug' => 'broken-php-type',
            'is_active' => true,
            'position' => 95,
        ]);

        // Broken PHP is the async scanner's problem now, not a synchronous gate.
        $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Broken PHP Plugin',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeZipUploadedFileWithPhp([
                'includes/broken.php' => "<?php\necho 'oops'\n",
            ]),
            'release_encapsulated' => 'unknown',
        ])->assertRedirect(route('plugins.index'));

        $this->assertTrue(PluginRelease::query()->exists());
    }

    public function test_broken_php_does_not_block_a_release_submission(): void
    {
        Notification::fake();
        Storage::fake('public');

        $owner = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Broken Release Type',
            'slug' => 'broken-release-type',
            'is_active' => true,
            'position' => 96,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Broken Release Plugin',
            'slug' => 'broken-release-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($owner)->post(route('plugins.release.store', $plugin), [
            'version' => '2.0.0',
            'zip_file' => $this->makeZipUploadedFileWithPhp([
                'includes/broken.php' => "<?php\necho 'oops'\n",
            ]),
            'encapsulated' => 'unknown',
        ])->assertRedirect(route('plugins.show', $plugin));

        $this->assertTrue(
            PluginRelease::query()->where('plugin_id', $plugin->id)->where('version', 'v2.0.0')->exists(),
        );
    }

    public function test_a_dangerous_function_does_not_block_submission(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Dangerous Fn Type',
            'slug' => 'dangerous-fn-type',
            'is_active' => true,
            'position' => 98,
        ]);

        // A dangerous function is for the async scan to flag/quarantine — the
        // upload itself still succeeds.
        $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Dangerous Fn Plugin',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeZipUploadedFileWithPhp([
                'includes/suspicious.php' => "<?php\neval(\$_GET['cmd']);\n",
            ]),
            'release_encapsulated' => 'unknown',
        ])->assertRedirect(route('plugins.index'));

        $this->assertTrue(PluginRelease::query()->exists());
    }

    public function test_submitting_a_plugin_with_a_path_traversal_entry_is_rejected(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Traversal Type',
            'slug' => 'traversal-type',
            'is_active' => true,
            'position' => 99,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Traversal Plugin',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeZipUploadedFileWithPhp([
                '../../evil.php' => "<?php\necho 'pwned';\n",
            ]),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.submit'));
        $response->assertSessionHasErrors(['release_zip_file']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Traversal Plugin']);
    }

    public function test_submitting_a_plugin_with_an_oversized_archive_is_rejected(): void
    {
        Notification::fake();
        Storage::fake('public');
        config(['filagora-plugins.archive.max_uncompressed_bytes' => 1000]);

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Oversized Type',
            'slug' => 'oversized-type',
            'is_active' => true,
            'position' => 100,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Oversized Plugin',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeZipUploadedFileWithPhp([
                'huge.txt' => str_repeat('a', 5000),
            ]),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.submit'));
        $response->assertSessionHasErrors(['release_zip_file']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Oversized Plugin']);
    }

    public function test_guest_post_to_plugin_store_redirects_to_login_modal(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Shipping Modules',
            'slug' => 'shipping-modules',
            'is_active' => true,
            'position' => 20,
        ]);

        $response = $this->post(route('plugins.store'), [
            'name' => 'Guest Attempt',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('guest.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect('/?auth=login&intended=%2Fplugins%2Fsubmit');
    }

    public function test_unverified_user_is_redirected_to_verify_flow_for_plugin_submit_routes(): void
    {
        $user = User::factory()->unverified()->create();
        $type = PluginType::query()->create([
            'name' => 'Unverified Type',
            'slug' => 'unverified-type',
            'is_active' => true,
            'position' => 22,
        ]);

        $this->actingAs($user)
            ->get(route('plugins.submit'))
            ->assertRedirect(route('verification.notice', ['intended' => '/plugins/submit']))
            ->assertSessionHas('url.intended', '/plugins/submit');

        $this->actingAs($user)
            ->post(route('plugins.store'), [
                'name' => 'Unverified Attempt',
                'plugin_type_id' => $type->id,
                'release_version' => '1.0.0',
                'release_zip_file' => $this->validZip('unverified.zip'),
                'release_encapsulated' => 'unknown',
            ])
            ->assertRedirect(route('verification.notice', ['intended' => '/plugins/submit']))
            ->assertSessionHas('url.intended', '/plugins/submit');
    }

    public function test_support_thread_search_returns_only_eligible_visible_threads(): void
    {
        $viewer = User::factory()->create();
        $otherAuthor = User::factory()->create();

        $publicGroup = ForumGroup::query()->create([
            'name' => 'Public Group',
            'slug' => 'public-group',
            'is_active' => true,
        ]);
        $supportForum = Forum::query()->create([
            'forum_group_id' => $publicGroup->id,
            'name' => 'Addon Support',
            'slug' => 'addon-support',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => true,
        ]);
        Thread::query()->create([
            'forum_id' => $supportForum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Visible Support Thread',
            'slug' => 'visible-support-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        Thread::query()->create([
            'forum_id' => $supportForum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Pending Support Thread',
            'slug' => 'pending-support-thread',
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        // Same visibility, but the forum is not flagged as a plugin support forum.
        $plainForum = Forum::query()->create([
            'forum_group_id' => $publicGroup->id,
            'name' => 'General Chat',
            'slug' => 'general-chat',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => false,
        ]);
        Thread::query()->create([
            'forum_id' => $plainForum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Off Topic Support Thread',
            'slug' => 'off-topic-support-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        // Support forum, but staff-only visibility.
        $staffGroup = ForumGroup::query()->create([
            'name' => 'Staff Group',
            'slug' => 'staff-group',
            'is_active' => true,
        ]);
        $staffForum = Forum::query()->create([
            'forum_group_id' => $staffGroup->id,
            'name' => 'Staff Only Support',
            'slug' => 'staff-only-support',
            'visibility' => ForumVisibility::STAFF_ONLY,
            'is_active' => true,
            'is_plugin_support_forum' => true,
        ]);
        Thread::query()->create([
            'forum_id' => $staffForum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Hidden Staff Support Thread',
            'slug' => 'hidden-staff-support-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $titles = collect(
            $this->actingAs($viewer)
                ->getJson(route('plugins.support-threads.search', ['q' => 'Support Thread']))
                ->assertOk()
                ->json('data')
        )->pluck('title');

        $this->assertTrue($titles->contains('Visible Support Thread'));
        $this->assertFalse($titles->contains('Pending Support Thread'));
        $this->assertFalse($titles->contains('Off Topic Support Thread'));
        $this->assertFalse($titles->contains('Hidden Staff Support Thread'));
    }

    public function test_support_thread_search_requires_authentication(): void
    {
        $this->getJson(route('plugins.support-threads.search', ['q' => 'anything']))
            ->assertUnauthorized();
    }

    public function test_plugin_store_validates_release_zip_file_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools',
            'is_active' => true,
            'position' => 30,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Bad Upload',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => UploadedFile::fake()->create('bad-upload.txt', 8, 'text/plain'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.submit'));
        $response->assertSessionHasErrors(['release_zip_file']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Bad Upload']);
    }

    public function test_plugin_store_rejects_hidden_forum_support_thread_selection(): void
    {
        Storage::fake('public');

        $viewer = User::factory()->create();
        $otherAuthor = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Submission Type',
            'slug' => 'submission-type',
            'is_active' => true,
            'position' => 25,
        ]);

        $staffGroup = ForumGroup::query()->create([
            'name' => 'Staff Only Group',
            'slug' => 'staff-only-group',
            'is_active' => true,
        ]);
        $staffForum = Forum::query()->create([
            'forum_group_id' => $staffGroup->id,
            'name' => 'Staff Support',
            'slug' => 'staff-support-store',
            'visibility' => ForumVisibility::STAFF_ONLY,
            'is_active' => true,
        ]);
        $hiddenThread = Thread::query()->create([
            'forum_id' => $staffForum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Hidden Staff Support Thread',
            'slug' => 'hidden-staff-support-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $response = $this->actingAs($viewer)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Hidden Support Thread Attempt',
            'plugin_type_id' => $type->id,
            'forum_thread_id' => $hiddenThread->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('hidden-support-thread-attempt.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.submit'));
        $response->assertSessionHasErrors(['forum_thread_id']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Hidden Support Thread Attempt']);
    }

    /**
     * @return array{0: User, 1: PluginType, 2: Forum}
     */
    private function pluginSubmitFixtures(string $slugSuffix): array
    {
        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Support URL Type '.$slugSuffix,
            'slug' => 'support-url-type-'.$slugSuffix,
            'is_active' => true,
            'position' => 41,
        ]);
        $group = ForumGroup::query()->create([
            'name' => 'Support URL Group '.$slugSuffix,
            'slug' => 'support-url-group-'.$slugSuffix,
            'is_active' => true,
        ]);
        $supportForum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Addon Support '.$slugSuffix,
            'slug' => 'addon-support-'.$slugSuffix,
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => true,
        ]);

        return [$user, $type, $supportForum];
    }

    public function test_submitting_a_plugin_can_link_a_support_thread_by_url(): void
    {
        Storage::fake('public');
        [$user, $type, $supportForum] = $this->pluginSubmitFixtures('link');

        $thread = Thread::query()->create([
            'forum_id' => $supportForum->id,
            'author_id' => $user->id,
            'title' => 'Existing Support Thread',
            'slug' => 'existing-support-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'URL Linked Plugin',
            'plugin_type_id' => $type->id,
            'support_thread_mode' => 'url',
            'support_thread_url' => url('/threads/'.$thread->id.'#post-1'),
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('url-linked-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.index'));
        $this->assertSame(
            (int) $thread->id,
            (int) Plugin::query()->where('name', 'URL Linked Plugin')->value('forum_thread_id'),
        );
    }

    public function test_support_thread_url_must_be_on_this_forum(): void
    {
        Storage::fake('public');
        [$user, $type, $supportForum] = $this->pluginSubmitFixtures('external');

        $thread = Thread::query()->create([
            'forum_id' => $supportForum->id,
            'author_id' => $user->id,
            'title' => 'Real Thread',
            'slug' => 'real-thread',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'External URL Plugin',
            'plugin_type_id' => $type->id,
            'support_thread_mode' => 'url',
            'support_thread_url' => 'https://evil.example.com/threads/'.$thread->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('external-url-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertSessionHasErrors(['support_thread_url']);
        $this->assertDatabaseMissing('plugins', ['name' => 'External URL Plugin']);
    }

    public function test_support_thread_url_must_point_to_a_viewable_support_thread(): void
    {
        Storage::fake('public');
        [$user, $type] = $this->pluginSubmitFixtures('plain');

        $plainGroup = ForumGroup::query()->create([
            'name' => 'Plain Group',
            'slug' => 'plain-group-url',
            'is_active' => true,
        ]);
        $plainForum = Forum::query()->create([
            'forum_group_id' => $plainGroup->id,
            'name' => 'General',
            'slug' => 'general-url',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => false,
        ]);
        $offTopicThread = Thread::query()->create([
            'forum_id' => $plainForum->id,
            'author_id' => $user->id,
            'title' => 'Off Topic',
            'slug' => 'off-topic-url',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Off Topic URL Plugin',
            'plugin_type_id' => $type->id,
            'support_thread_mode' => 'url',
            'support_thread_url' => url('/threads/'.$offTopicThread->id),
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('off-topic-url-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertSessionHasErrors(['support_thread_url']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Off Topic URL Plugin']);
    }

    public function test_existing_support_thread_selection_must_be_in_a_plugin_support_forum(): void
    {
        Storage::fake('public');
        [$user, $type] = $this->pluginSubmitFixtures('scoped');

        $plainGroup = ForumGroup::query()->create([
            'name' => 'Plain Group Scoped',
            'slug' => 'plain-group-scoped',
            'is_active' => true,
        ]);
        $plainForum = Forum::query()->create([
            'forum_group_id' => $plainGroup->id,
            'name' => 'General Scoped',
            'slug' => 'general-scoped',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
            'is_plugin_support_forum' => false,
        ]);
        $plainThread = Thread::query()->create([
            'forum_id' => $plainForum->id,
            'author_id' => $user->id,
            'title' => 'Visible But Not A Support Forum',
            'slug' => 'visible-not-support',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Wrong Forum Thread Plugin',
            'plugin_type_id' => $type->id,
            'support_thread_mode' => 'existing',
            'forum_thread_id' => $plainThread->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->validZip('wrong-forum-thread-plugin.zip'),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertSessionHasErrors(['forum_thread_id']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Wrong Forum Thread Plugin']);
    }

    public function test_plugin_store_rejects_encapsulated_archive_without_canonical_root(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Encapsulated Validation Type',
            'slug' => 'encapsulated-validation-type',
            'is_active' => true,
            'position' => 35,
        ]);

        $response = $this->actingAs($user)->from(route('plugins.submit'))->post(route('plugins.store'), [
            'name' => 'Ambiguous Encapsulated Upload',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => UploadedFile::fake()->createWithContent('ambiguous-upload.zip', $this->makeAmbiguousZip()),
            'release_encapsulated' => 'yes',
        ]);

        $response->assertRedirect(route('plugins.submit'));
        $response->assertSessionHasErrors(['release_zip_file']);
        $this->assertDatabaseMissing('plugins', ['name' => 'Ambiguous Encapsulated Upload']);
    }

    public function test_inactive_plugin_returns_404_for_new_release_form_and_submit(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Inactive Type',
            'slug' => 'inactive-type',
            'is_active' => true,
            'position' => 40,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Inactive Plugin',
            'slug' => 'inactive-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        $zcVersion = ZcVersion::query()->create([
            'version' => '1.5.8',
            'is_active' => true,
            'position' => 20,
        ]);

        $this->actingAs($user)
            ->get(route('plugins.release.new', $plugin))
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('plugins.release.store', $plugin), [
                'version' => '2.0.0',
                'zip_file' => $this->validZip('inactive.zip'),
                'encapsulated' => 'no',
                'zc_versions' => [$zcVersion->id],
            ])
            ->assertNotFound();
    }

    public function test_authenticated_user_can_submit_release_for_active_plugin(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Active Type',
            'slug' => 'active-type',
            'is_active' => true,
            'position' => 50,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Release Plugin',
            'slug' => 'release-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $zcVersion = ZcVersion::query()->create([
            'version' => '2.1.0',
            'is_active' => true,
            'position' => 10,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.release.store', $plugin), [
            'version' => '2.0.1',
            'zip_file' => UploadedFile::fake()->createWithContent('release-plugin.zip', $this->makeZipWithRoot('release_plugin')),
            'changelog' => 'Bugfix release',
            'encapsulated' => 'yes',
            'zc_versions' => [$zcVersion->id],
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_success');

        $release = PluginRelease::query()
            ->where('plugin_id', $plugin->id)
            ->where('version', 'v2.0.1')
            ->firstOrFail();

        $this->assertSame(PluginStatus::MODERATED, $release->status);
        $this->assertSame('yes', $release->encapsulated->value);
        $this->assertSame('release_plugin', $plugin->fresh()->zip_root);
        Storage::disk('public')->assertExists($release->zip_path);
        $this->assertDatabaseHas('plugin_release_zc_versions', [
            'plugin_release_id' => $release->id,
            'plugin_zc_version_id' => $zcVersion->id,
        ]);
        Notification::assertSentTo($user, PluginNotification::class);
        Notification::assertSentOnDemand(PluginNotification::class, fn (PluginNotification $notification, array $channels, object $notifiable): bool => $notification->kind === 'submitted_support' && ($notifiable->routes['mail'] ?? null) === 'plugin_support@zen-cart.com');
    }

    public function test_authenticated_user_can_submit_encapsulated_plugin_with_derived_zip_root(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Encapsulated Happy Path Type',
            'slug' => 'encapsulated-happy-path-type',
            'is_active' => true,
            'position' => 51,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Encapsulated Gateway',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => UploadedFile::fake()->createWithContent('encapsulated-gateway.zip', $this->makeZipWithRoot('encapsulated_gateway')),
            'release_encapsulated' => 'yes',
        ]);

        $response->assertRedirect(route('plugins.index'));

        $plugin = Plugin::query()->where('name', 'Encapsulated Gateway')->firstOrFail();
        $release = PluginRelease::query()->where('plugin_id', $plugin->id)->firstOrFail();

        $this->assertSame('encapsulated_gateway', $plugin->zip_root);
        $this->assertSame('yes', $release->encapsulated->value);
    }

    public function test_submitting_a_release_with_a_version_that_already_exists_fails_validation_instead_of_500ing(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Duplicate Version Type',
            'slug' => 'duplicate-version-type',
            'is_active' => true,
            'position' => 50,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Duplicate Version Plugin',
            'slug' => 'duplicate-version-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '3.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/existing.zip',
            'zip_filename' => 'existing.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);

        $response = $this->actingAs($user)->post(route('plugins.release.store', $plugin), [
            'version' => '3.0.0',
            'zip_file' => UploadedFile::fake()->createWithContent('release-plugin.zip', $this->makeZipWithRoot('release_plugin')),
            'encapsulated' => 'yes',
        ]);

        $response->assertSessionHasErrors(['version' => ReleaseRefusal::DUPLICATE_VERSION->message()]);
        $this->assertSame(1, PluginRelease::query()->where('plugin_id', $plugin->id)->where('version', '3.0.0')->count());
    }

    public function test_submitting_a_release_with_an_invalid_version_format_fails_validation(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Invalid Version Type',
            'slug' => 'invalid-version-type',
            'is_active' => true,
            'position' => 51,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Invalid Version Plugin',
            'slug' => 'invalid-version-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.release.store', $plugin), [
            'version' => 'Version 1.0',
            'zip_file' => UploadedFile::fake()->createWithContent('release-plugin.zip', $this->makeZipWithRoot('release_plugin')),
            'encapsulated' => 'unknown',
        ]);

        $response->assertSessionHasErrors(['version' => ReleaseRefusal::INVALID_VERSION->message()]);
        $this->assertSame(0, PluginRelease::query()->where('plugin_id', $plugin->id)->count());
    }

    public function test_submitting_a_release_whose_canonical_version_matches_a_legacy_bare_row_fails_validation(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Legacy Version Type',
            'slug' => 'legacy-version-type',
            'is_active' => true,
            'position' => 52,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Legacy Version Plugin',
            'slug' => 'legacy-version-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '1.0.1',
            'internal_version' => 1,
            'zip_path' => 'plugins/existing.zip',
            'zip_filename' => 'existing.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);

        $response = $this->actingAs($user)->post(route('plugins.release.store', $plugin), [
            'version' => 'v1.0.1',
            'zip_file' => UploadedFile::fake()->createWithContent('release-plugin.zip', $this->makeZipWithRoot('release_plugin')),
            'encapsulated' => 'unknown',
        ]);

        $response->assertSessionHasErrors(['version' => ReleaseRefusal::DUPLICATE_VERSION->message()]);
        $this->assertSame(1, PluginRelease::query()->where('plugin_id', $plugin->id)->count());
    }

    public function test_plugin_auto_publish_entitlement_makes_release_live_immediately(): void
    {
        Notification::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'operator.plugin.auto_publish',
            'grant_type' => 'manual',
            'granted_by_id' => $user->id,
        ]);

        $type = PluginType::query()->create([
            'name' => 'Trusted Release Type',
            'slug' => 'trusted-release-type',
            'is_active' => true,
            'position' => 52,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Trusted Release Plugin',
            'slug' => 'trusted-release-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $zcVersion = ZcVersion::query()->create([
            'version' => '2.1.1',
            'is_active' => true,
            'position' => 12,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.release.store', $plugin), [
            'version' => '2.0.2',
            'zip_file' => UploadedFile::fake()->createWithContent('trusted-release-plugin.zip', $this->makeZipWithRoot('trusted_release_plugin')),
            'changelog' => 'Trusted bugfix release',
            'encapsulated' => 'yes',
            'zc_versions' => [$zcVersion->id],
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_success', __('plugin.flash.release_live'));

        $release = PluginRelease::query()
            ->where('plugin_id', $plugin->id)
            ->where('version', 'v2.0.2')
            ->firstOrFail();

        $this->assertSame(PluginStatus::ACTIVE, $release->status);
        $this->assertSame('trusted_release_plugin', $plugin->fresh()->zip_root);

        Notification::assertSentTo($user, PluginNotification::class, fn (PluginNotification $notification): bool => $notification->kind === 'submitted_live');
        Notification::assertSentOnDemand(PluginNotification::class, fn (PluginNotification $notification, array $channels, object $notifiable): bool => $notification->kind === 'submitted_support' && ($notifiable->routes['mail'] ?? null) === 'plugin_support@zen-cart.com');
    }

    public function test_submitting_a_plugin_with_backslash_separated_zip_entries_is_repaired_on_upload(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Backslash Repair Type',
            'slug' => 'backslash-repair-type',
            'is_active' => true,
            'position' => 97,
        ]);

        $response = $this->actingAs($user)->post(route('plugins.store'), [
            'name' => 'Backslash Repair Plugin',
            'plugin_type_id' => $type->id,
            'release_version' => '1.0.0',
            'release_zip_file' => $this->makeZipUploadedFileWithPhp([
                'Widget\\README.md' => 'hello',
                'Widget\\zc_plugins\\Widget\\manifest.php' => "<?php\nreturn ['pluginId' => 0];\n",
            ]),
            'release_encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.index'));

        $plugin = Plugin::query()->where('name', 'Backslash Repair Plugin')->firstOrFail();
        $release = PluginRelease::query()->where('plugin_id', $plugin->id)->firstOrFail();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('public')->path($release->zip_path));
        $this->assertNotFalse($zip->locateName('Widget/README.md'));
        $this->assertNotFalse($zip->locateName('Widget/zc_plugins/Widget/manifest.php'));
        $this->assertFalse($zip->locateName('Widget\\README.md'));
        $zip->close();
    }

    public function test_submitting_a_release_with_backslash_separated_zip_entries_is_repaired_on_upload(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Release Backslash Repair Type',
            'slug' => 'release-backslash-repair-type',
            'is_active' => true,
            'position' => 98,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Release Backslash Repair Plugin',
            'slug' => 'release-backslash-repair-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($owner)->post(route('plugins.release.store', $plugin), [
            'version' => '2.0.0',
            'zip_file' => $this->makeZipUploadedFileWithPhp([
                'Widget\\README.md' => 'hello',
                'Widget\\zc_plugins\\Widget\\manifest.php' => "<?php\nreturn ['pluginId' => 0];\n",
            ]),
            'encapsulated' => 'unknown',
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));

        $release = PluginRelease::query()->where('plugin_id', $plugin->id)->where('version', 'v2.0.0')->firstOrFail();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('public')->path($release->zip_path));
        $this->assertNotFalse($zip->locateName('Widget/README.md'));
        $this->assertNotFalse($zip->locateName('Widget/zc_plugins/Widget/manifest.php'));
        $this->assertFalse($zip->locateName('Widget\\README.md'));
        $zip->close();
    }

    public function test_submitting_an_encapsulated_release_whose_zip_version_folder_mismatches_the_typed_version_fails_validation(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Version Folder Mismatch Type',
            'slug' => 'version-folder-mismatch-type',
            'is_active' => true,
            'position' => 59,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Version Folder Mismatch Plugin',
            'slug' => 'version-folder-mismatch-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $manifestEntry = 'zc_plugins/VersionFolderWidget/v1.0.0/manifest.php';

        $response = $this->actingAs($owner)->post(route('plugins.release.store', $plugin), [
            'version' => '1.0.1',
            'zip_file' => $this->makeManifestUploadedFile($manifestEntry, 0),
            'encapsulated' => 'yes',
        ]);

        $response->assertSessionHasErrors(['zip_file']);
        $this->assertSame(0, PluginRelease::query()->where('plugin_id', $plugin->id)->count());
    }

    public function test_submitting_an_encapsulated_release_whose_zip_version_folder_matches_the_typed_version_stores_the_folder(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Version Folder Match Type',
            'slug' => 'version-folder-match-type',
            'is_active' => true,
            'position' => 60,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Version Folder Match Plugin',
            'slug' => 'version-folder-match-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $manifestEntry = 'zc_plugins/VersionFolderMatchWidget/v1.0.0/manifest.php';

        $response = $this->actingAs($owner)->post(route('plugins.release.store', $plugin), [
            'version' => '1.0.0',
            'zip_file' => $this->makeManifestUploadedFile($manifestEntry, 0),
            'encapsulated' => 'yes',
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));

        $release = $plugin->releases()->where('version', 'v1.0.0')->firstOrFail();
        $this->assertSame('v1.0.0', $release->version_folder);
    }

    public function test_non_owner_non_staff_can_submit_release_for_active_plugin_pending_moderation(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Release Permission Type',
            'slug' => 'release-permission-type',
            'is_active' => true,
            'position' => 55,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Owner Release Plugin',
            'slug' => 'owner-release-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($otherUser)->post(route('plugins.release.store', $plugin), [
            'version' => '2.0.1',
            'zip_file' => UploadedFile::fake()->createWithContent('release-plugin.zip', $this->makeZipWithRoot('owner_release_plugin')),
            'encapsulated' => 'yes',
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_success', __('plugin.flash.release_pending_review'));
        $this->assertDatabaseHas('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => 'v2.0.1',
            'status' => PluginStatus::MODERATED->value,
            'uploaded_by_id' => $otherUser->id,
        ]);
    }

    public function test_non_owner_non_staff_can_submit_release_for_ownerless_plugin_pending_moderation(): void
    {
        Storage::fake('public');

        $otherUser = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Abandoned Plugin Type',
            'slug' => 'abandoned-plugin-type',
            'is_active' => true,
            'position' => 57,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Abandoned Plugin',
            'slug' => 'abandoned-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => null,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($otherUser)->post(route('plugins.release.store', $plugin), [
            'version' => '2.0.1',
            'zip_file' => UploadedFile::fake()->createWithContent('abandoned-plugin.zip', $this->makeZipWithRoot('abandoned_plugin')),
            'encapsulated' => 'yes',
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_success', __('plugin.flash.release_pending_review'));
        $this->assertDatabaseHas('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => 'v2.0.1',
            'status' => PluginStatus::MODERATED->value,
            'uploaded_by_id' => $otherUser->id,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filagora-plugins.mail.support_email', 'plugin_support@zen-cart.com');
    }

    public function test_staff_can_submit_release_for_active_plugin_they_do_not_own(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $staff = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $staff->id,
            'entitlement_key' => 'operator.plugin.moderate',
            'grant_type' => 'manual',
            'granted_by_id' => $staff->id,
        ]);

        $type = PluginType::query()->create([
            'name' => 'Staff Permission Type',
            'slug' => 'staff-permission-type',
            'is_active' => true,
            'position' => 56,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Staff Release Plugin',
            'slug' => 'staff-release-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($staff)->post(route('plugins.release.store', $plugin), [
            'version' => '2.1.0',
            'zip_file' => UploadedFile::fake()->createWithContent('staff-release-plugin.zip', $this->makeZipWithRoot('staff_release_plugin')),
            'encapsulated' => 'yes',
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_success', __('plugin.flash.release_live'));
        $this->assertDatabaseHas('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => 'v2.1.0',
            'status' => PluginStatus::ACTIVE->value,
            'uploaded_by_id' => $staff->id,
        ]);
    }

    public function test_guest_post_to_release_store_redirects_to_login_modal(): void
    {
        $type = PluginType::query()->create([
            'name' => 'Guest Type',
            'slug' => 'guest-type',
            'is_active' => true,
            'position' => 60,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Guest Plugin',
            'slug' => 'guest-plugin',
            'plugin_type_id' => $type->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->post(route('plugins.release.store', $plugin), [
            'version' => '1.0.0',
            'zip_file' => $this->validZip('guest-release.zip'),
            'encapsulated' => 'unknown',
        ]);

        $response->assertRedirect('/?auth=login&intended=%2Fplugins%2F'.$plugin->slug.'%2Freleases%2Fnew');
    }

    public function test_unverified_user_is_redirected_to_verify_flow_for_plugin_release_routes(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->unverified()->create();
        $type = PluginType::query()->create([
            'name' => 'Unverified Release Type',
            'slug' => 'unverified-release-type',
            'is_active' => true,
            'position' => 61,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Unverified Release Plugin',
            'slug' => 'unverified-release-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($user)
            ->get(route('plugins.release.new', $plugin))
            ->assertRedirect(route('verification.notice', ['intended' => '/plugins/'.$plugin->slug.'/releases/new']))
            ->assertSessionHas('url.intended', '/plugins/'.$plugin->slug.'/releases/new');

        $this->actingAs($user)
            ->post(route('plugins.release.store', $plugin), [
                'version' => '1.0.0',
                'zip_file' => $this->validZip('unverified-release.zip'),
                'encapsulated' => 'unknown',
            ])
            ->assertRedirect(route('verification.notice', ['intended' => '/plugins/'.$plugin->slug.'/releases/new']))
            ->assertSessionHas('url.intended', '/plugins/'.$plugin->slug.'/releases/new');
    }

    public function test_release_store_validates_zip_file_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Validation Type',
            'slug' => 'validation-type',
            'is_active' => true,
            'position' => 70,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Validation Plugin',
            'slug' => 'validation-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($user)
            ->from(route('plugins.release.new', $plugin))
            ->post(route('plugins.release.store', $plugin), [
                'version' => '1.1.0',
                'zip_file' => UploadedFile::fake()->create('invalid.txt', 8, 'text/plain'),
                'encapsulated' => 'no',
            ]);

        $response->assertRedirect(route('plugins.release.new', $plugin));
        $response->assertSessionHasErrors(['zip_file']);
        $this->assertDatabaseMissing('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => '1.1.0',
        ]);
    }

    public function test_release_store_rejects_encapsulated_archive_without_canonical_root(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Release Ambiguity Type',
            'slug' => 'release-ambiguity-type',
            'is_active' => true,
            'position' => 71,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Release Ambiguity Plugin',
            'slug' => 'release-ambiguity-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($user)
            ->from(route('plugins.release.new', $plugin))
            ->post(route('plugins.release.store', $plugin), [
                'version' => '1.1.0',
                'zip_file' => UploadedFile::fake()->createWithContent('ambiguous-release.zip', $this->makeAmbiguousZip()),
                'encapsulated' => 'yes',
            ]);

        $response->assertRedirect(route('plugins.release.new', $plugin));
        $response->assertSessionHasErrors(['zip_file']);
        $this->assertDatabaseMissing('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => '1.1.0',
        ]);
    }

    public function test_release_store_rejects_encapsulated_archive_when_root_changes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Root Stability Type',
            'slug' => 'root-stability-type',
            'is_active' => true,
            'position' => 72,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Root Stability Plugin',
            'slug' => 'root-stability-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
            'zip_root' => 'stable_plugin',
        ]);

        $response = $this->actingAs($user)
            ->from(route('plugins.release.new', $plugin))
            ->post(route('plugins.release.store', $plugin), [
                'version' => '2.0.0',
                'zip_file' => UploadedFile::fake()->createWithContent('changed-root-release.zip', $this->makeZipWithRoot('different_plugin')),
                'encapsulated' => 'yes',
            ]);

        $response->assertRedirect(route('plugins.release.new', $plugin));
        $response->assertSessionHasErrors(['zip_file']);
        $this->assertDatabaseMissing('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => '2.0.0',
        ]);
    }

    public function test_release_store_accepts_encapsulated_archive_despite_unresolvable_earlier_releases(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Legacy History Type',
            'slug' => 'legacy-history-type',
            'is_active' => true,
            'position' => 74,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Legacy History Plugin',
            'slug' => 'legacy-history-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        Storage::disk('public')->put('plugins/legacy-history-plugin-1.0.0.zip', $this->makeAmbiguousZip());
        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/legacy-history-plugin-1.0.0.zip',
            'zip_filename' => 'legacy-history-plugin-1.0.0.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'yes',
        ]);

        $response = $this->actingAs($user)
            ->from(route('plugins.release.new', $plugin))
            ->post(route('plugins.release.store', $plugin), [
                'version' => '2.0.0',
                'zip_file' => UploadedFile::fake()->createWithContent('legacy-history-release.zip', $this->makeZipWithRoot('legacy_history_plugin')),
                'encapsulated' => 'yes',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plugin_releases', [
            'plugin_id' => $plugin->id,
            'version' => 'v2.0.0',
        ]);
        $this->assertSame('legacy_history_plugin', $plugin->fresh()->zip_root);
    }

    public function test_new_release_form_preselects_zc_versions_from_the_previous_release(): void
    {
        $user = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Preselect Type',
            'slug' => 'preselect-type',
            'is_active' => true,
            'position' => 73,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Preselect Plugin',
            'slug' => 'preselect-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $carriedVersion = ZcVersion::query()->create([
            'version' => '1.5.7',
            'is_active' => true,
            'position' => 1,
        ]);
        $unrelatedVersion = ZcVersion::query()->create([
            'version' => '2.2.0',
            'is_active' => true,
            'position' => 2,
        ]);

        $previousRelease = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/preselect-plugin-1.0.0.zip',
            'zip_filename' => 'preselect-plugin-1.0.0.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);
        $previousRelease->zcVersions()->sync([$carriedVersion->id]);

        $html = $this->actingAs($user)->get(route('plugins.release.new', $plugin))->getContent();

        $this->assertMatchesRegularExpression(
            '/value="'.$carriedVersion->id.'"[^>]*checked/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/value="'.$unrelatedVersion->id.'"[^>]*checked/',
            $html
        );
    }

    public function test_active_plugin_with_inactive_type_returns_404_for_release_routes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $inactiveType = PluginType::query()->create([
            'name' => 'Inactive Parent Type',
            'slug' => 'inactive-parent-type',
            'is_active' => false,
            'position' => 80,
        ]);
        $plugin = Plugin::query()->create([
            'name' => 'Type-blocked Plugin',
            'slug' => 'type-blocked-plugin',
            'plugin_type_id' => $inactiveType->id,
            'owner_id' => $user->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($user)
            ->get(route('plugins.release.new', $plugin))
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('plugins.release.store', $plugin), [
                'version' => '1.2.0',
                'zip_file' => $this->validZip('blocked.zip'),
                'encapsulated' => 'unknown',
            ])
            ->assertNotFound();
    }

    /**
     * @param  array<string, string>  $phpEntries
     */
    private function validZip(string $filename = 'plugin.zip'): UploadedFile
    {
        return $this->makeZipUploadedFileWithPhp(['plugin.php' => "<?php\nreturn true;\n"], $filename);
    }

    private function makeZipUploadedFileWithPhp(array $phpEntries, string $filename = 'plugin.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'plugin-lint-upload-');
        if (! is_string($path)) {
            $this->fail('Failed to create temporary zip file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Failed to open temporary zip file.');
        }

        foreach ($phpEntries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }

        $zip->close();

        return new UploadedFile($path, $filename, 'application/zip', null, true);
    }

    private function makeManifestUploadedFile(string $manifestEntry, int $pluginIdPlaceholder): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'plugin-manifest-upload-');
        if (! is_string($path)) {
            $this->fail('Failed to create temporary zip file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Failed to open temporary zip file.');
        }

        $zip->addFromString($manifestEntry, "<?php\nreturn [\n    'pluginId' => {$pluginIdPlaceholder},\n];\n");
        $zip->close();

        return new UploadedFile($path, 'manifest-widget.zip', 'application/zip', null, true);
    }

    private function readManifestFromStoredZip(string $zipPath, string $manifestEntry): string
    {
        $absolutePath = Storage::disk('public')->path($zipPath);

        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            $this->fail('Failed to open stored zip file for assertion.');
        }

        $contents = $zip->getFromName($manifestEntry);
        $zip->close();

        if (! is_string($contents)) {
            $this->fail('Manifest entry not found in stored zip.');
        }

        return $contents;
    }

    private function makeZipWithRoot(string $rootDirectory): string
    {
        $path = tempnam(sys_get_temp_dir(), 'plugin-zip-root-');
        if (! is_string($path)) {
            $this->fail('Failed to create temporary zip file.');
        }

        $zip = new ZipArchive;
        $result = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            $this->fail('Failed to open temporary zip file.');
        }

        $zip->addEmptyDir($rootDirectory);
        $zip->addFromString($rootDirectory.'/manifest.json', '{}');
        $zip->close();

        $contents = file_get_contents($path);
        @unlink($path);

        if (! is_string($contents)) {
            $this->fail('Failed to read temporary zip file.');
        }

        return $contents;
    }

    private function makeAmbiguousZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'plugin-zip-ambiguous-');
        if (! is_string($path)) {
            $this->fail('Failed to create temporary zip file.');
        }

        $zip = new ZipArchive;
        $result = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            $this->fail('Failed to open temporary zip file.');
        }

        $zip->addFromString('plugin_alpha/manifest.json', '{}');
        $zip->addFromString('plugin_beta/manifest.json', '{}');
        $zip->close();

        $contents = file_get_contents($path);
        @unlink($path);

        if (! is_string($contents)) {
            $this->fail('Failed to read temporary zip file.');
        }

        return $contents;
    }
}
