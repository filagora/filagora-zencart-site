<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BackupFailedNotification;
use App\Notifications\BellNotification;
use App\Notifications\PluginReportMailNotification;
use App\Notifications\SecurityMailNotification;
use App\Notifications\StaffAlerts;
use App\Notifications\StaffCategories;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\NotificationCategories;
use Filagora\Notifications\Services\Preferences;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Events\PluginReported;
use Filagora\Plugins\Models\PluginReport;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * Alerts about things staff handle go to the shared team address by default; with
 * `staff_alerts_to_individuals` on they go to the staff who hold the area and want them, each on the
 * channels they chose.
 */
class StaffAlertsTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private const TEAM = 'team@example.test';

    private function individualMode(bool $on = true): void
    {
        app(SettingsRegistry::class)->set(StaffAlerts::SETTING, $on);
    }

    private function moderator(string $entitlement = 'operator.plugin.moderate'): User
    {
        $user = User::factory()->create();
        $this->grantEntitlement($user, $entitlement);

        return $user;
    }

    private function turnOff(User $user, string $category, NotificationChannel $channel): void
    {
        app(Preferences::class)->set($user, $category, $channel, false);
        app(Preferences::class)->flush();
    }

    private function alert(string $category = StaffCategories::PLUGIN_REPORTS, bool $guaranteed = false): void
    {
        app(StaffAlerts::class)->send(
            $category,
            fn () => new BackupFailedNotification('testing', 'https://example.test'),
            [self::TEAM],
            fn () => new BellNotification('Title', 'Body', '/somewhere', $category),
            $guaranteed,
        );
    }

    public function test_by_default_the_alert_goes_to_the_team_address_and_to_no_individual(): void
    {
        Notification::fake();
        $moderator = $this->moderator();

        $this->alert();

        Notification::assertSentOnDemand(BackupFailedNotification::class, fn ($n, $channels, AnonymousNotifiable $to): bool => $to->routes['mail'] === self::TEAM);
        Notification::assertNotSentTo($moderator, BackupFailedNotification::class);
        Notification::assertNotSentTo($moderator, BellNotification::class);
    }

    public function test_in_individual_mode_each_holder_is_mailed_and_belled_and_the_team_address_is_left_out(): void
    {
        Notification::fake();
        $this->individualMode();
        $moderator = $this->moderator();
        $stranger = User::factory()->create();

        $this->alert();

        Notification::assertSentTo($moderator, BackupFailedNotification::class);
        Notification::assertSentTo($moderator, BellNotification::class);
        Notification::assertNotSentTo($stranger, BackupFailedNotification::class);
        Notification::assertNotSentTo($stranger, BellNotification::class);
        Notification::assertSentOnDemandTimes(BackupFailedNotification::class, 0);
    }

    public function test_a_moderator_who_turns_a_channel_off_is_not_told_on_it_and_off_everywhere_means_not_told(): void
    {
        Notification::fake();
        $this->individualMode();
        $emailOff = $this->moderator();
        $allOff = $this->moderator();
        $this->turnOff($emailOff, StaffCategories::PLUGIN_REPORTS, NotificationChannel::EMAIL);
        $this->turnOff($allOff, StaffCategories::PLUGIN_REPORTS, NotificationChannel::EMAIL);
        $this->turnOff($allOff, StaffCategories::PLUGIN_REPORTS, NotificationChannel::IN_APP);

        $this->alert();

        Notification::assertNotSentTo($emailOff, BackupFailedNotification::class);
        Notification::assertSentTo($emailOff, BellNotification::class);
        Notification::assertNotSentTo($allOff, BackupFailedNotification::class);
        Notification::assertNotSentTo($allOff, BellNotification::class);
    }

    public function test_only_the_holders_of_the_area_are_told(): void
    {
        Notification::fake();
        $this->individualMode();
        $pluginModerator = $this->moderator('operator.plugin.moderate');
        $privacyOfficer = $this->moderator('operator.privacy.requests.update');

        $this->alert(StaffCategories::PLUGIN_REPORTS);

        Notification::assertSentTo($pluginModerator, BackupFailedNotification::class);
        Notification::assertNotSentTo($privacyOfficer, BackupFailedNotification::class);
    }

    public function test_a_wildcard_grant_counts_as_holding_the_area(): void
    {
        Notification::fake();
        $this->individualMode();
        $admin = $this->moderator('operator.*');

        $this->alert();

        Notification::assertSentTo($admin, BackupFailedNotification::class);
    }

    public function test_a_super_administrator_is_told_through_the_role_alone(): void
    {
        Notification::fake();
        $this->individualMode();
        $super = User::factory()->create();
        $super->assignRole((string) config('filagora-zencart.super_admin_role'));

        $this->alert();

        Notification::assertSentTo($super, BackupFailedNotification::class);
        Notification::assertSentTo($super, BellNotification::class);
    }

    public function test_a_privacy_request_nobody_is_set_to_hear_about_still_reaches_the_team_and_is_logged(): void
    {
        Notification::fake();
        Log::spy();
        $this->individualMode();
        $officer = $this->moderator('operator.privacy.requests.update');
        $this->turnOff($officer, StaffCategories::PRIVACY_REQUESTS, NotificationChannel::EMAIL);
        $this->turnOff($officer, StaffCategories::PRIVACY_REQUESTS, NotificationChannel::IN_APP);

        $this->alert(StaffCategories::PRIVACY_REQUESTS, guaranteed: true);

        Notification::assertNotSentTo($officer, BackupFailedNotification::class);
        Notification::assertSentOnDemand(BackupFailedNotification::class, fn ($n, $channels, AnonymousNotifiable $to): bool => $to->routes['mail'] === self::TEAM);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_other_alerts_nobody_wants_are_not_sent_to_the_team(): void
    {
        Notification::fake();
        $this->individualMode();

        $this->alert(StaffCategories::PLUGIN_REPORTS);

        Notification::assertNothingSent();
    }

    public function test_a_reported_plugin_tells_the_moderators_by_email_and_bell(): void
    {
        Notification::fake();
        $this->individualMode();
        $moderator = $this->moderator();
        [$plugin, $release, $owner] = $this->pluginWithRelease();
        $report = PluginReport::query()->forceCreate(['plugin_id' => $plugin->id, 'plugin_release_id' => $release->id, 'reporter_id' => $owner->id, 'reason' => 'It phones home.', 'status' => 'open']);

        event(new PluginReported($report));

        Notification::assertSentTo($moderator, PluginReportMailNotification::class);
        Notification::assertSentTo($moderator, BellNotification::class, fn (BellNotification $bell): bool => ($bell->toArray($moderator)['category'] ?? null) === StaffCategories::PLUGIN_REPORTS);
    }

    public function test_the_security_packages_support_address_mail_stands_down_only_in_individual_mode(): void
    {
        [, $release] = $this->pluginWithRelease();
        $flagged = new SecurityMailNotification('finding_flagged', $release, forStaff: true);

        $this->assertSame(['mail'], $flagged->via(new AnonymousNotifiable));

        $this->individualMode();

        $this->assertSame([], $flagged->via(new AnonymousNotifiable));
        $this->assertSame(['mail'], $flagged->via($this->moderator()));
    }

    public function test_a_moderator_is_offered_only_their_areas_on_their_profile_and_a_member_none(): void
    {
        $moderator = $this->moderator();
        $member = User::factory()->create();
        $prefs = app(Preferences::class);

        $ids = fn (User $user): array => array_map(fn (array $row): string => $row['category']->id, $prefs->matrix($user));

        $this->assertContains(StaffCategories::PLUGIN_REPORTS, $ids($moderator));
        $this->assertNotContains(StaffCategories::PRIVACY_REQUESTS, $ids($moderator));
        $this->assertSame([], array_values(array_filter($ids($member), fn (string $id): bool => str_starts_with($id, 'staff.'))));
    }

    public function test_the_profile_shows_a_moderator_their_staff_alerts_only_once_alerts_go_to_individuals(): void
    {
        $moderator = $this->acknowledged($this->moderator());
        $member = $this->acknowledged(User::factory()->create());

        $this->actingAs($moderator)->get('/profile?tab=settings')->assertOk()->assertDontSee('Staff alerts')->assertDontSee('Plugin reports');

        $this->individualMode();

        $this->actingAs($moderator)->get('/profile?tab=settings')->assertOk()->assertSee('Staff alerts')->assertSee('Plugin reports')->assertDontSee('New privacy requests');
        $this->actingAs($member)->get('/profile?tab=settings')->assertOk()->assertDontSee('Staff alerts');
    }

    public function test_saving_the_profile_while_the_staff_section_is_hidden_leaves_the_moderators_choices_alone(): void
    {
        $moderator = $this->acknowledged($this->moderator());
        $prefs = app(Preferences::class);
        $prefs->set($moderator, StaffCategories::PLUGIN_REPORTS, NotificationChannel::IN_APP, false);
        $prefs->flush();

        $this->actingAs($moderator)->post(route('profile.notifications.update'), [])->assertRedirect('/profile?tab=settings');
        $prefs->flush();

        // The hidden section sent no checkboxes; that must not read as "turned everything off".
        $this->assertTrue($prefs->allows($moderator, StaffCategories::PLUGIN_SUBMISSIONS, NotificationChannel::EMAIL));
        $this->assertTrue($prefs->allows($moderator, StaffCategories::PLUGIN_REPORTS, NotificationChannel::EMAIL));
        $this->assertFalse($prefs->allows($moderator, StaffCategories::PLUGIN_REPORTS, NotificationChannel::IN_APP));
    }

    public function test_once_staff_alerts_go_to_individuals_saving_the_profile_stores_the_staff_choices(): void
    {
        $this->individualMode();
        $moderator = $this->acknowledged($this->moderator());
        $prefs = app(Preferences::class);

        $this->actingAs($moderator)->post(route('profile.notifications.update'), ['channel' => [StaffCategories::PLUGIN_REPORTS => ['email' => '1']]])->assertRedirect('/profile?tab=settings');
        $prefs->flush();

        $this->assertTrue($prefs->allows($moderator, StaffCategories::PLUGIN_REPORTS, NotificationChannel::EMAIL));
        $this->assertFalse($prefs->allows($moderator, StaffCategories::PLUGIN_REPORTS, NotificationChannel::IN_APP));
        $this->assertFalse($prefs->allows($moderator, StaffCategories::PLUGIN_SUBMISSIONS, NotificationChannel::EMAIL));
    }

    private function acknowledged(User $user): User
    {
        $policy = PrivacyPolicyVersion::query()->where('is_active', true)->orderByDesc('published_at')->orderByDesc('id')->first();

        if ($policy instanceof PrivacyPolicyVersion) {
            UserPrivacyPolicyAcceptance::query()->updateOrCreate(
                ['user_id' => $user->id, 'privacy_policy_version_id' => $policy->id],
                ['accepted_at' => now(), 'ip_hash' => hash('sha256', '127.0.0.1'), 'user_agent_hash' => hash('sha256', 'phpunit')],
            );
        }

        return $user;
    }

    public function test_the_two_system_alerts_offer_email_only_because_they_have_no_bell(): void
    {
        $channels = fn (string $id): array => array_map(fn ($c) => $c->value, app(NotificationCategories::class)->get($id)->channels);

        $this->assertSame(['email'], $channels(StaffCategories::BACKUP_FAILURES));
        $this->assertSame(['email'], $channels(StaffCategories::SEARCH_HEALTH));
        $this->assertSame(['email', 'in_app'], $channels(StaffCategories::PLUGIN_SUBMISSIONS));
    }

    /**
     * @return array{0: Plugin, 1: PluginRelease, 2: User}
     */
    private function pluginWithRelease(): array
    {
        $owner = User::factory()->create();
        $type = PluginType::query()->create(['name' => 'Admin Tools', 'slug' => 'admin-tools', 'is_active' => true, 'position' => 30]);
        $plugin = Plugin::query()->create(['name' => 'Admin Audit Trail', 'slug' => 'admin-audit-trail', 'plugin_type_id' => $type->id, 'owner_id' => $owner->id, 'status' => PluginStatus::ACTIVE->value]);
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

        return [$plugin, $release, $owner];
    }
}
