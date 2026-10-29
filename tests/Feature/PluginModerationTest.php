<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PluginMailNotification;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Filament\Resources\PluginReleases\PluginReleaseResource;
use Filagora\Plugins\Filament\Resources\Plugins\PluginResource;
use Filagora\Plugins\Models\PluginModeratorNote;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;
use ZipArchive;

class PluginModerationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_can_approve_moderated_plugin_and_release(): void
    {
        Notification::fake();

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $owner = $plugin->owner()->firstOrFail();
        $uploader = $release->uploadedBy()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('plugins.moderate', $plugin), [
                'action' => 'approve',
                'note' => 'Looks good.',
            ])
            ->assertRedirect()
            ->assertSessionHas('plugin_success', __('plugin.moderation.flash.plugin_approved', ['plugin' => $plugin->name]));

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
                'note' => 'Release approved.',
            ])
            ->assertRedirect()
            ->assertSessionHas('plugin_success', __('plugin.moderation.flash.release_approved', ['plugin' => $plugin->name]));

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $this->assertDatabaseHas('plugin_releases', [
            'id' => $release->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $this->assertGreaterThanOrEqual(2, PluginModeratorNote::query()->count());
        $this->assertNotNull($release->fresh()->approved_at);
        Notification::assertSentTo($owner, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'plugin_approved');
        Notification::assertSentTo($uploader, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_approved');
    }

    public function test_approving_the_first_release_also_approves_a_still_moderated_plugin(): void
    {
        Notification::fake();

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $owner = $release->uploadedBy()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
                'note' => 'Release approved.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $this->assertDatabaseHas('plugin_releases', [
            'id' => $release->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        Notification::assertSentTo($owner, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_approved');
    }

    public function test_approving_a_release_leaves_an_already_rejected_plugin_rejected(): void
    {
        Notification::fake();

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $plugin->update(['status' => PluginStatus::REJECTED_FINAL->value]);

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
                'note' => 'Release approved.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'status' => PluginStatus::REJECTED_FINAL->value,
        ]);
        Notification::assertNotSentTo($plugin->owner()->firstOrFail(), PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'plugin_approved');
    }

    public function test_approving_a_release_sets_approved_at_independently_of_an_old_creation_date(): void
    {
        // Regression test: a release's zip can be uploaded (and its DB row
        // created) long before it's actually approved. "Latest Plugins"
        // ordering must key off when it was approved, not created_at.
        Notification::fake();

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $release->forceFill(['created_at' => now()->subMonths(6)])->saveQuietly();

        $this->actingAs($staff)
            ->post(route('plugins.moderate', $plugin), [
                'action' => 'approve',
                'note' => 'Looks good.',
            ]);

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
                'note' => 'Release approved.',
            ])
            ->assertRedirect();

        $release->refresh();

        $this->assertTrue($release->created_at->lt(now()->subMonths(5)));
        $this->assertNotNull($release->approved_at);
        $this->assertTrue($release->approved_at->gt(now()->subMinute()));
    }

    public function test_rejecting_a_release_does_not_set_approved_at(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'reject_permanently',
                'note' => 'Not ready yet.',
            ])
            ->assertRedirect();

        $this->assertNull($release->fresh()->approved_at);
    }

    public function test_approving_a_release_updates_the_manifest_plugin_id_to_the_real_id(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $manifestEntry = 'zc_plugins/PendingPluginZip/v1.0.0/manifest.php';
        $this->putManifestZip($release->zip_path, $manifestEntry, 0);

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
                'note' => 'Release approved.',
            ])
            ->assertRedirect();

        $this->assertStringContainsString(
            "'pluginId' => {$plugin->canonicalId()},",
            $this->readManifestFromStoredZip($release->zip_path, $manifestEntry)
        );
    }

    public function test_approving_a_release_writes_the_canonical_legacy_id_into_the_manifest(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $plugin->update(['legacy_vbulletin_file_id' => 2431]);
        $manifestEntry = 'zc_plugins/PendingPluginZip/v1.0.0/manifest.php';
        $this->putManifestZip($release->zip_path, $manifestEntry, 0);

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
                'note' => 'Release approved.',
            ])
            ->assertRedirect();

        $manifest = $this->readManifestFromStoredZip($release->zip_path, $manifestEntry);
        $this->assertStringContainsString("'pluginId' => 2431,", $manifest);
        $this->assertStringNotContainsString("'pluginId' => {$plugin->id},", $manifest);
    }

    public function test_reject_requires_note_for_staff_actions(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();

        $this->actingAs($staff)
            ->from('/plugins')
            ->post(route('plugins.moderate', $plugin), [
                'action' => 'reject_permanently',
                'note' => '',
            ])
            ->assertSessionHasErrors(['note']);

        $this->actingAs($staff)
            ->from('/plugins')
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'reject_permanently',
                'note' => '',
            ])
            ->assertSessionHasErrors(['note']);
    }

    public function test_reject_sends_email_to_plugin_owner_and_release_author(): void
    {
        Notification::fake();

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $owner = $plugin->owner()->firstOrFail();
        $uploader = $release->uploadedBy()->firstOrFail();

        $this->actingAs($staff)
            ->from('/plugins')
            ->post(route('plugins.moderate', $plugin), [
                'action' => 'reject_permanently',
                'note' => 'Insufficient documentation.',
            ])
            ->assertRedirect('/plugins')
            ->assertSessionHas('plugin_keep_queue_open', true);

        $this->actingAs($staff)
            ->from('/plugins')
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'reject_permanently',
                'note' => 'Package contents are invalid.',
            ])
            ->assertRedirect('/plugins')
            ->assertSessionHas('plugin_keep_queue_open', true);

        Notification::assertSentTo($owner, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'plugin_rejected');
        Notification::assertSentTo($uploader, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'release_rejected');
    }

    public function test_non_staff_cannot_moderate_plugin_or_release(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $nonStaff = User::factory()->create();

        $this->actingAs($nonStaff)
            ->post(route('plugins.moderate', $plugin), [
                'action' => 'approve',
            ])
            ->assertForbidden();

        $this->actingAs($nonStaff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'approve',
            ])
            ->assertForbidden();
    }

    public function test_staff_moderation_queue_shows_plugin_and_release_authors(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();

        $plugin->load('owner');
        $release->load('uploadedBy');

        $this->actingAs($staff)
            ->get(route('plugins.index'))
            ->assertOk()
            ->assertSeeText('Author:')
            ->assertSeeText($plugin->owner->username)
            ->assertSeeText('Release author:')
            ->assertSeeText($release->uploadedBy->username)
            ->assertSee(route('members.show', ['user' => $plugin->owner->id]), false)
            ->assertSee(route('members.show', ['user' => $release->uploadedBy->id]), false);
    }

    public function test_staff_can_delete_plugin_with_releases_and_zip_files(): void
    {
        Storage::fake('public');
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        Storage::disk('public')->put($release->zip_path, 'zip');

        $this->actingAs($staff)
            ->delete(route('plugins.destroy', $plugin), ['reason' => 'Malware'])
            ->assertRedirect(route('plugins.index'))
            ->assertSessionHas('plugin_keep_queue_open', true);

        $this->assertDatabaseMissing('plugins', ['id' => $plugin->id]);
        $this->assertDatabaseMissing('plugin_releases', ['id' => $release->id]);
        Storage::disk('public')->assertMissing($release->zip_path);
        $this->assertFalse($plugin->owner()->firstOrFail()->fresh()->is_banned);
    }

    public function test_deleting_a_plugin_can_remove_all_author_content_and_ban(): void
    {
        Storage::fake('public');
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $owner = $plugin->owner()->firstOrFail();

        $otherPlugin = Plugin::query()->create([
            'name' => 'Second Plugin',
            'slug' => 'second-plugin',
            'plugin_type_id' => $plugin->plugin_type_id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $bystander = Plugin::query()->create([
            'name' => 'Bystander',
            'slug' => 'bystander',
            'plugin_type_id' => $plugin->plugin_type_id,
            'owner_id' => User::factory()->create()->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $ownersReleaseOnOthersPlugin = PluginRelease::query()->create([
            'plugin_id' => $bystander->id,
            'uploaded_by_id' => $owner->id,
            'version' => '2.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/owner-on-other.zip',
            'zip_filename' => 'owner-on-other.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $this->actingAs($staff)
            ->delete(route('plugins.destroy', $plugin), [
                'remove_all_by_author' => 1,
                'ban' => 1,
                'ban_reason' => 'Spam plugins',
            ])
            ->assertRedirect(route('plugins.index'));

        $this->assertDatabaseMissing('plugins', ['id' => $plugin->id]);
        $this->assertDatabaseMissing('plugins', ['id' => $otherPlugin->id]);
        $this->assertDatabaseMissing('plugin_releases', ['id' => $ownersReleaseOnOthersPlugin->id]);
        $this->assertDatabaseHas('plugins', ['id' => $bystander->id]);

        $owner->refresh();
        $this->assertTrue($owner->is_banned);
        $this->assertNull($owner->banned_until);
        $this->assertSame('Spam plugins', $owner->ban_reason);
    }

    public function test_staff_can_delete_a_single_release_without_touching_the_plugin(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();

        $this->actingAs($staff)
            ->delete(route('plugins.release.destroy', ['plugin' => $plugin, 'release' => $release]))
            ->assertRedirect(route('plugins.index'));

        $this->assertDatabaseMissing('plugin_releases', ['id' => $release->id]);
        $this->assertDatabaseHas('plugins', ['id' => $plugin->id]);
        $this->assertFalse($release->uploadedBy()->firstOrFail()->fresh()->is_banned);
    }

    public function test_deleting_a_release_can_remove_all_uploader_releases_and_ban(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $uploader = $release->uploadedBy()->firstOrFail();
        $second = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => '1.1.0',
            'internal_version' => 2,
            'zip_path' => 'plugins/second.zip',
            'zip_filename' => 'second.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $this->actingAs($staff)
            ->delete(route('plugins.release.destroy', ['plugin' => $plugin, 'release' => $release]), [
                'remove_all_by_author' => 1,
                'ban' => 1,
            ])
            ->assertRedirect(route('plugins.index'));

        $this->assertDatabaseMissing('plugin_releases', ['id' => $release->id]);
        $this->assertDatabaseMissing('plugin_releases', ['id' => $second->id]);
        $this->assertDatabaseHas('plugins', ['id' => $plugin->id]);
        $this->assertTrue($uploader->fresh()->is_banned);
    }

    public function test_moderator_cannot_ban_or_sweep_themselves_when_deleting(): void
    {
        [$staff, $plugin] = $this->createPendingPluginWithRelease();
        $plugin->update(['owner_id' => $staff->id]);

        $this->actingAs($staff)
            ->delete(route('plugins.destroy', $plugin), ['remove_all_by_author' => 1, 'ban' => 1])
            ->assertRedirect(route('plugins.index'));

        $this->assertDatabaseMissing('plugins', ['id' => $plugin->id]);
        $this->assertFalse($staff->fresh()->is_banned);
    }

    public function test_non_staff_cannot_delete_plugins_or_releases(): void
    {
        [, $plugin, $release] = $this->createPendingPluginWithRelease();
        $user = User::factory()->create();

        $this->actingAs($user)->delete(route('plugins.destroy', $plugin))->assertForbidden();
        $this->actingAs($user)
            ->delete(route('plugins.release.destroy', ['plugin' => $plugin, 'release' => $release]))
            ->assertForbidden();

        $this->assertDatabaseHas('plugins', ['id' => $plugin->id]);
        $this->assertDatabaseHas('plugin_releases', ['id' => $release->id]);
    }

    public function test_queue_reject_buttons_open_the_reject_dialog_for_each_item(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();

        $this->actingAs($staff)
            ->get(route('plugins.index'))
            ->assertOk()
            ->assertSee('data-plugin-reject-dialog', false)
            ->assertSee('data-plugin-reject-open', false)
            ->assertSee('data-action="'.route('plugins.moderate', $plugin).'"', false)
            ->assertSee('data-action="'.route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]).'"', false);
    }

    public function test_staff_can_grant_auto_publish_entitlement_to_plugin_author_from_queue(): void
    {
        Notification::fake();

        [$staff, $plugin] = $this->createPendingPluginWithRelease();
        $author = $plugin->owner()->firstOrFail();

        $this->actingAs($staff)
            ->from('/plugins')
            ->post(route('plugins.grant-auto-publish', $plugin))
            ->assertRedirect('/plugins')
            ->assertSessionHas('plugin_success', __('plugin.moderation.flash.auto_publish_plugin_author_enabled', ['author' => $author->username]))
            ->assertSessionHas('plugin_keep_queue_open', true);

        $author->refresh();

        $this->assertDatabaseHas('user_entitlement_grants', [
            'user_id' => $author->id,
            'entitlement_key' => 'operator.plugin.auto_publish',
            'grant_type' => 'manual',
            'revoked_at' => null,
        ]);
    }

    public function test_staff_can_grant_auto_publish_entitlement_to_release_author_from_queue(): void
    {
        Notification::fake();

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $author = $release->uploadedBy()->firstOrFail();

        $this->actingAs($staff)
            ->from('/plugins')
            ->post(route('plugins.release.grant-auto-publish', ['plugin' => $plugin, 'release' => $release]))
            ->assertRedirect('/plugins')
            ->assertSessionHas('plugin_success', __('plugin.moderation.flash.auto_publish_release_author_enabled', ['author' => $author->username]))
            ->assertSessionHas('plugin_keep_queue_open', true);

        $author->refresh();

        $this->assertDatabaseHas('user_entitlement_grants', [
            'user_id' => $author->id,
            'entitlement_key' => 'operator.plugin.auto_publish',
            'grant_type' => 'manual',
            'revoked_at' => null,
        ]);
    }

    public function test_the_retired_temporary_reject_action_is_no_longer_accepted(): void
    {
        $staff = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $staff->id,
            'entitlement_key' => 'operator.plugin.moderate',
            'grant_type' => 'manual',
            'granted_by_id' => $staff->id,
        ]);
        $owner = User::factory()->create();

        $type = PluginType::query()->create([
            'name' => 'Accepted Type',
            'slug' => 'accepted-type',
            'is_active' => true,
            'position' => 20,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Previously Accepted Plugin',
            'slug' => 'previously-accepted-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($staff)
            ->post(route('plugins.moderate', $plugin), [
                'action' => 'reject',
                'note' => 'A security issue was found.',
            ])
            ->assertSessionHasErrors(['action']);

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
    }

    public function test_staff_can_reject_plugin(): void
    {
        Notification::fake();

        $staff = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $staff->id,
            'entitlement_key' => 'operator.plugin.moderate',
            'grant_type' => 'manual',
            'granted_by_id' => $staff->id,
        ]);
        $owner = User::factory()->create();

        $type = PluginType::query()->create([
            'name' => 'Reject Type',
            'slug' => 'reject-type',
            'is_active' => true,
            'position' => 22,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Reject Plugin',
            'slug' => 'reject-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($staff)
            ->post(route('plugins.moderate', $plugin), [
                'action' => 'reject_permanently',
                'note' => 'This plugin is disallowed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'status' => PluginStatus::REJECTED_FINAL->value,
        ]);
        Notification::assertSentTo($owner, PluginMailNotification::class, fn (PluginMailNotification $n): bool => $n->kind === 'plugin_rejected');
    }

    public function test_moderator_note_does_not_double_up_a_version_the_uploader_already_prefixed_with_v(): void
    {
        Notification::fake();

        $staff = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $staff->id,
            'entitlement_key' => 'operator.plugin.moderate',
            'grant_type' => 'manual',
            'granted_by_id' => $staff->id,
        ]);
        $owner = User::factory()->create();

        $type = PluginType::query()->create([
            'name' => 'V Prefix Type',
            'slug' => 'v-prefix-type',
            'is_active' => true,
            'position' => 23,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'V Prefix Plugin',
            'slug' => 'v-prefix-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => 'v3.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/v-prefix-plugin.zip',
            'zip_filename' => 'v-prefix-plugin.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $this->actingAs($staff)
            ->post(route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]), [
                'action' => 'reject_permanently',
                'note' => "You haven't fixed the bugs noted in the issue tracker.",
            ])
            ->assertRedirect();

        $moderatorNote = PluginModeratorNote::query()->where('plugin_id', $plugin->id)->latest('id')->first();

        $this->assertNotNull($moderatorNote);
        $this->assertStringStartsWith('Release v3.0.0 rejected.', $moderatorNote->note);
        $this->assertStringNotContainsString('vv3.0.0', $moderatorNote->note);
    }

    public function test_moderator_can_download_pending_release_for_review(): void
    {
        Storage::fake('public');

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        Storage::disk('public')->put($release->zip_path, 'zip contents');

        $this->actingAs($staff)
            ->get(route('plugins.release.download', ['plugin' => $plugin, 'release' => $release]))
            ->assertOk()
            ->assertDownload($release->zip_filename);

        $this->actingAs($staff)
            ->get(route('plugins.download', $plugin))
            ->assertOk()
            ->assertDownload($release->zip_filename);

        $this->assertDatabaseHas('plugin_releases', [
            'id' => $release->id,
            'download_count' => 0,
        ]);
    }

    public function test_moderator_can_download_pending_plugin_gated_by_an_entitlement(): void
    {
        Storage::fake('public');

        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $plugin->type()->firstOrFail()->update(['required_entitlement_scope' => 'plugins.commercial']);
        Storage::disk('public')->put($release->zip_path, 'zip contents');

        $this->actingAs($staff)
            ->get(route('plugins.release.download', ['plugin' => $plugin, 'release' => $release]))
            ->assertOk()
            ->assertDownload($release->zip_filename);
    }

    public function test_non_moderator_cannot_download_pending_release(): void
    {
        Storage::fake('public');

        [, $plugin, $release] = $this->createPendingPluginWithRelease();
        Storage::disk('public')->put($release->zip_path, 'zip contents');

        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('plugins.release.download', ['plugin' => $plugin, 'release' => $release]))
            ->assertNotFound();

        $this->actingAs($member)
            ->get(route('plugins.download', $plugin))
            ->assertNotFound();

        $this->get(route('plugins.download', $plugin))->assertNotFound();
    }

    public function test_pending_plugin_page_offers_the_review_download_to_moderators(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();

        $this->actingAs($staff)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee(route('plugins.release.download', ['plugin' => $plugin, 'release' => $release]), false)
            ->assertSee('Download for review');
    }

    public function test_pending_release_on_a_public_plugin_offers_the_review_download_to_moderators(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $plugin->update(['status' => PluginStatus::ACTIVE->value]);
        $releaseDownloadUrl = route('plugins.release.download', ['plugin' => $plugin, 'release' => $release]);

        $this->actingAs($staff)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee($releaseDownloadUrl, false)
            ->assertSee('Download for review');

        // The same page for a member keeps the unapproved release out of sight.
        $this->actingAs(User::factory()->create())
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee($releaseDownloadUrl, false)
            ->assertDontSee('Download for review');
    }

    public function test_moderation_queue_links_to_downloads_for_review(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();

        $this->actingAs($staff)
            ->get(route('plugins.index'))
            ->assertOk()
            ->assertSee(route('plugins.download', $plugin), false)
            ->assertSee(route('plugins.release.download', ['plugin' => $plugin, 'release' => $release]), false);
    }

    public function test_plugin_show_page_links_to_dashboard_edit_pages_for_moderators_only(): void
    {
        [$staff, $plugin, $release] = $this->createPendingPluginWithRelease();
        $editPluginUrl = PluginResource::getUrl('edit', ['record' => $plugin]);
        $editReleaseUrl = PluginReleaseResource::getUrl('edit', ['record' => $release]);

        $this->actingAs($staff)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee($editPluginUrl, false)
            ->assertSee($editReleaseUrl, false)
            ->assertSee('Edit Plugin')
            ->assertSee('Edit Release');

        $this->actingAs(User::factory()->create())
            ->get(route('plugins.show', $plugin))
            ->assertNotFound();

        $plugin->update(['status' => PluginStatus::ACTIVE->value]);
        $release->update(['status' => PluginStatus::ACTIVE->value]);

        $this->actingAs(User::factory()->create())
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee($release->version)
            ->assertDontSee($editPluginUrl, false)
            ->assertDontSee($editReleaseUrl, false)
            ->assertDontSee('Edit Plugin')
            ->assertDontSee('Edit Release');
    }

    /**
     * @return array{0: User, 1: Plugin, 2: PluginRelease}
     */
    private function createPendingPluginWithRelease(): array
    {
        $staff = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $staff->id,
            'entitlement_key' => 'operator.plugin.moderate',
            'grant_type' => 'manual',
            'granted_by_id' => $staff->id,
        ]);

        $owner = User::factory()->create();
        $uploader = User::factory()->create();

        $type = PluginType::query()->create([
            'name' => 'Moderation Type',
            'slug' => 'moderation-type',
            'is_active' => true,
            'position' => 10,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Pending Moderation Plugin',
            'slug' => 'pending-moderation-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::MODERATED->value,
        ]);

        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $uploader->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/pending-moderation.zip',
            'zip_filename' => 'pending-moderation.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        return [$staff, $plugin, $release];
    }

    private function putManifestZip(string $zipPath, string $manifestEntry, int $pluginIdPlaceholder): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'plugin-manifest-moderation-');
        if (! is_string($tempPath)) {
            $this->fail('Failed to create temporary zip file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Failed to open temporary zip file.');
        }

        $zip->addFromString($manifestEntry, "<?php\nreturn [\n    'pluginId' => {$pluginIdPlaceholder},\n];\n");
        $zip->close();

        $contents = file_get_contents($tempPath);
        @unlink($tempPath);

        if (! is_string($contents)) {
            $this->fail('Failed to read temporary zip file.');
        }

        Storage::disk('public')->put($zipPath, $contents);
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
}
