<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StaffAlertRecipients;
use App\Notifications\StaffAlerts;
use App\Notifications\StaffCategories;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Recipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * The page an administrator uses to check who staff alerts would reach before switching them to
 * individuals. It asks the sender's own questions, so it cannot disagree with what is sent.
 */
class StaffAlertRecipientsPageTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private function staff(string $name, string ...$entitlements): User
    {
        $user = User::factory()->create(['username' => $name]);
        $this->grantEntitlements($user, $entitlements);

        return $user;
    }

    /**
     * @return array<string, array{people: list<array{name: string, wants: array<string, bool>}>, told: int}>
     */
    private function reportById(): array
    {
        $byId = [];

        foreach (app(StaffAlertRecipients::class)->report() as $row) {
            $byId[$row['id']] = $row;
        }

        return $byId;
    }

    public function test_it_lists_the_holders_of_each_area_and_not_other_staff(): void
    {
        $this->staff('pluginmod', 'operator.plugin.moderate');
        $this->staff('privacyofficer', 'operator.privacy.requests.update');

        $report = $this->reportById();

        $this->assertSame(['pluginmod'], array_column($report[StaffCategories::PLUGIN_REPORTS]['people'], 'name'));
        $this->assertSame(['privacyofficer'], array_column($report[StaffCategories::PRIVACY_REQUESTS]['people'], 'name'));
        $this->assertSame([], $report[StaffCategories::BACKUP_FAILURES]['people']);
    }

    public function test_it_shows_each_persons_channels_and_how_many_would_be_told(): void
    {
        $emailOnly = $this->staff('emailonly', 'operator.plugin.moderate');
        $allOff = $this->staff('alloff', 'operator.plugin.moderate');
        $prefs = app(Preferences::class);
        $prefs->set($emailOnly, StaffCategories::PLUGIN_REPORTS, NotificationChannel::IN_APP, false);
        $prefs->set($allOff, StaffCategories::PLUGIN_REPORTS, NotificationChannel::EMAIL, false);
        $prefs->set($allOff, StaffCategories::PLUGIN_REPORTS, NotificationChannel::IN_APP, false);
        $prefs->flush();

        $row = $this->reportById()[StaffCategories::PLUGIN_REPORTS];
        $people = array_column($row['people'], null, 'name');

        $this->assertSame(['email' => true, 'in_app' => false], $people['emailonly']['wants']);
        $this->assertSame(['email' => false, 'in_app' => false], $people['alloff']['wants']);
        $this->assertSame(1, $row['told']);
    }

    public function test_it_agrees_with_the_sender(): void
    {
        $this->staff('pluginmod', 'operator.plugin.moderate');
        $person = $this->staff('quiet', 'operator.plugin.moderate');
        app(Preferences::class)->set($person, StaffCategories::PLUGIN_SUBMISSIONS, NotificationChannel::EMAIL, false);
        app(Preferences::class)->flush();

        $listed = array_column(array_filter($this->reportById()[StaffCategories::PLUGIN_SUBMISSIONS]['people'], fn (array $p): bool => $p['wants']['email']), 'name');
        $sent = array_map(fn ($user): string => $user->username, app(Recipients::class)->for(StaffCategories::PLUGIN_SUBMISSIONS));

        $this->assertSame($sent, $listed);
    }

    public function test_a_super_administrator_holds_every_area_through_the_role_alone(): void
    {
        $super = User::factory()->create(['username' => 'superadmin']);
        $super->assignRole((string) config('filagora-zencart.super_admin_role'));

        foreach ($this->reportById() as $id => $row) {
            $this->assertContains('superadmin', array_column($row['people'], 'name'), $id);
        }
    }

    public function test_an_administrator_sees_the_page_and_other_staff_do_not(): void
    {
        $this->staff('pluginmod', 'operator.plugin.moderate');
        $admin = $this->staff('admin', 'operator.system.settings.manage', 'operator.dashboard.access');
        $other = $this->staff('other', 'operator.plugin.moderate', 'operator.dashboard.access');

        $this->actingAs($admin)->get('/dashboard/staff-alert-recipients')
            ->assertOk()
            ->assertSee('Staff Alert Recipients')
            ->assertSee('Plugin reports')
            ->assertSee('pluginmod')
            ->assertSee('currently go to the shared team addresses');

        $this->actingAs($other)->get('/dashboard/staff-alert-recipients')->assertForbidden();
    }

    public function test_it_says_when_alerts_already_go_to_individuals(): void
    {
        $admin = $this->staff('admin', 'operator.system.settings.manage', 'operator.dashboard.access');
        app(SettingsRegistry::class)->set(StaffAlerts::SETTING, true);

        $this->actingAs($admin)->get('/dashboard/staff-alert-recipients')->assertOk()->assertSee('go to the individual staff listed below');
    }

    public function test_an_area_nobody_holds_is_flagged(): void
    {
        $admin = $this->staff('admin', 'operator.system.settings.manage', 'operator.dashboard.access');

        $this->actingAs($admin)->get('/dashboard/staff-alert-recipients')->assertOk()->assertSee('Nobody holds this area');
    }
}
