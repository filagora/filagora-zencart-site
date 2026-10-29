<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PluginReportMailNotification;
use Filagora\Plugins\Enums\PluginReportStatus;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginReport;
use Filagora\Plugins\Models\PluginType;
use Filagora\Plugins\Services\Reports;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginReportTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_verified_user_can_report_plugin(): void
    {
        Notification::fake();
        [$plugin] = $this->createPlugin();
        $reporter = User::factory()->create();
        config()->set('filagora-plugins.mail.support_email', 'plugins@example.test');

        $response = $this->actingAs($reporter)->post(route('plugins.report', $plugin), [
            'reason' => 'This plugin contains malicious code',
        ]);

        $response->assertRedirect(route('plugins.show', $plugin));
        $response->assertSessionHas('plugin_success', __('plugin.reports.submitted'));
        $this->assertDatabaseHas('plugin_reports', [
            'plugin_id' => $plugin->id,
            'plugin_release_id' => null,
            'reporter_id' => $reporter->id,
            'status' => PluginReportStatus::OPEN->value,
        ]);
        Notification::assertSentOnDemand(PluginReportMailNotification::class, fn (PluginReportMailNotification $notification, array $channels, object $notifiable): bool => $notification->kind === 'report_submitted' && ($notifiable->routes['mail'] ?? null) === 'plugins@example.test');
    }

    public function test_verified_user_can_report_a_specific_release(): void
    {
        Notification::fake();
        [$plugin, , $release] = $this->createPlugin();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post(route('plugins.report', $plugin), [
            'reason' => 'This release has a broken changelog link',
            'plugin_release_id' => $release->id,
        ])->assertRedirect(route('plugins.show', $plugin));

        $this->assertDatabaseHas('plugin_reports', [
            'plugin_id' => $plugin->id,
            'plugin_release_id' => $release->id,
            'reporter_id' => $reporter->id,
        ]);
    }

    public function test_report_aborts_when_release_belongs_to_a_different_plugin(): void
    {
        [$plugin] = $this->createPlugin();
        [, , $otherRelease] = $this->createPlugin();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post(route('plugins.report', $plugin), [
            'reason' => 'Mismatched release',
            'plugin_release_id' => $otherRelease->id,
        ])->assertNotFound();
    }

    public function test_duplicate_open_report_by_same_user_is_blocked(): void
    {
        [$plugin] = $this->createPlugin();
        $reporter = User::factory()->create();

        PluginReport::query()->create([
            'plugin_id' => $plugin->id,
            'reporter_id' => $reporter->id,
            'reason' => 'Initial report',
            'status' => PluginReportStatus::OPEN->value,
        ]);
        Notification::fake();

        $response = $this->actingAs($reporter)->post(route('plugins.report', $plugin), [
            'reason' => 'Second report',
        ]);

        $response->assertSessionHas('plugin_error', __('plugin.reports.already_reported'));
        $this->assertSame(1, PluginReport::query()->count());
        Notification::assertSentOnDemandTimes(PluginReportMailNotification::class, 0);
    }

    public function test_guest_report_redirects_to_login_modal(): void
    {
        Notification::fake();
        [$plugin] = $this->createPlugin();

        $this->post(route('plugins.report', $plugin), [
            'reason' => 'Report reason',
        ])->assertRedirect('/?auth=login&intended=%2Fplugins%2F'.$plugin->slug);
        Notification::assertSentOnDemandTimes(PluginReportMailNotification::class, 0);
    }

    public function test_unverified_user_cannot_report_plugins(): void
    {
        Notification::fake();
        [$plugin] = $this->createPlugin();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('plugins.report', $plugin), [
                'reason' => 'Report reason',
            ])
            ->assertRedirect(route('verification.notice', [
                'intended' => '/plugins/'.$plugin->slug.'/report',
            ]));
        Notification::assertSentOnDemandTimes(PluginReportMailNotification::class, 0);
    }

    public function test_reporting_does_not_send_staff_email_if_plugin_support_email_setting_is_missing_or_invalid(): void
    {
        Notification::fake();
        [$plugin] = $this->createPlugin();
        $reporter = User::factory()->create();

        config()->set('filagora-plugins.mail.support_email', null);
        $this->actingAs($reporter)->post(route('plugins.report', $plugin), [
            'reason' => 'Missing setting case',
        ]);
        Notification::assertSentOnDemandTimes(PluginReportMailNotification::class, 0);

        config()->set('filagora-plugins.mail.support_email', 'not-an-email');
        $anotherReporter = User::factory()->create();
        $this->actingAs($anotherReporter)->post(route('plugins.report', $plugin), [
            'reason' => 'Invalid setting case',
        ]);
        Notification::assertSentOnDemandTimes(PluginReportMailNotification::class, 0);
    }

    public function test_plugin_page_shows_report_button_for_verified_non_owner_and_hides_for_owner(): void
    {
        [$plugin, $owner] = $this->createPlugin();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertSee('data-report-open', false);

        $this->actingAs($owner)
            ->get(route('plugins.show', $plugin))
            ->assertOk()
            ->assertDontSee('data-report-open', false);
    }

    public function test_reporter_is_emailed_when_report_status_changes(): void
    {
        Notification::fake();

        [$plugin] = $this->createPlugin();
        $reporter = User::factory()->create();

        $report = PluginReport::query()->create([
            'plugin_id' => $plugin->id,
            'reporter_id' => $reporter->id,
            'reason' => 'This plugin contains malicious code',
            'status' => PluginReportStatus::OPEN->value,
        ]);

        Notification::assertSentToTimes($reporter, PluginReportMailNotification::class, 0);

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantEntitlement($moderator, 'operator.plugin.moderate');
        app(Reports::class)->review($moderator, $report, PluginReportStatus::REVIEWED, 'We reviewed the report and took action.');

        Notification::assertSentTo($reporter, PluginReportMailNotification::class, fn (PluginReportMailNotification $notification): bool => $notification->kind === 'report_reviewed');
    }

    public function test_reporter_is_not_emailed_when_report_is_saved_without_status_change(): void
    {
        Notification::fake();

        [$plugin] = $this->createPlugin();
        $reporter = User::factory()->create();

        $report = PluginReport::query()->create([
            'plugin_id' => $plugin->id,
            'reporter_id' => $reporter->id,
            'reason' => 'This plugin contains malicious code',
            'status' => PluginReportStatus::OPEN->value,
        ]);

        $report->update([
            'resolution_note' => 'Needs another moderator review.',
        ]);

        Notification::assertSentToTimes($reporter, PluginReportMailNotification::class, 0);
    }

    /**
     * @return array{0: Plugin, 1: User, 2: PluginRelease}
     */
    private function createPlugin(): array
    {
        $owner = User::factory()->create();
        $type = PluginType::query()->create([
            'name' => 'Admin Tools',
            'slug' => 'admin-tools-'.uniqid(),
            'is_active' => true,
            'position' => 30,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Reportable Plugin',
            'slug' => 'reportable-plugin-'.uniqid(),
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

        return [$plugin, $owner, $release];
    }
}
