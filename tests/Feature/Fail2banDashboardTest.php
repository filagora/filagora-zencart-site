<?php

namespace Tests\Feature;

use App\Filament\Pages\Fail2banDashboard;
use App\Models\User;
use App\Services\Fail2banService;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class Fail2banDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_super_admin_can_view_fail2ban_dashboard(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.fail2ban.view');

        $service = Mockery::mock(Fail2banService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->once()
            ->andReturn([
                'enabled' => true,
                'note' => 'Host wrapper command in use.',
                'jail_count' => 1,
                'jails' => [[
                    'name' => 'sshd',
                    'currently_failed' => 1,
                    'total_failed' => 12,
                    'currently_banned' => 2,
                    'total_banned' => 8,
                    'banned_ips' => ['203.0.113.10', '2001:db8::10'],
                    'log_files' => [],
                    'journal_matches' => '_SYSTEMD_UNIT=ssh.service + _COMM=sshd',
                    'raw_output' => 'Status for the jail: sshd',
                ]],
            ]);

        $this->app->instance(Fail2banService::class, $service);

        $this->actingAs($user)
            ->get('/dashboard/fail2ban')
            ->assertOk()
            ->assertSee('Fail2ban')
            ->assertSee('sshd')
            ->assertSee('203.0.113.10')
            ->assertSee('Host wrapper command in use.')
            ->assertSee('Inspect configured jails, current bans, and failed attempts');
    }

    public function test_forum_moderator_is_forbidden_from_fail2ban_dashboard(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.forum.moderate');

        $this->actingAs($user)
            ->get('/dashboard/fail2ban')
            ->assertForbidden();
    }

    public function test_regular_user_is_redirected_from_fail2ban_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard/fail2ban')
            ->assertRedirect(route('home'));
    }

    public function test_refresh_clears_stale_ip_when_selected_jail_no_longer_has_active_bans(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.fail2ban.view');

        $initialSnapshot = [
            'enabled' => true,
            'note' => null,
            'jail_count' => 1,
            'jails' => [[
                'name' => 'sshd',
                'currently_failed' => 1,
                'total_failed' => 12,
                'currently_banned' => 1,
                'total_banned' => 8,
                'banned_ips' => ['203.0.113.10'],
                'log_files' => [],
                'journal_matches' => '_SYSTEMD_UNIT=ssh.service + _COMM=sshd',
                'raw_output' => 'Status for the jail: sshd',
            ]],
        ];

        $refreshedSnapshot = [
            'enabled' => true,
            'note' => null,
            'jail_count' => 1,
            'jails' => [[
                'name' => 'sshd',
                'currently_failed' => 2,
                'total_failed' => 13,
                'currently_banned' => 0,
                'total_banned' => 8,
                'banned_ips' => [],
                'log_files' => [],
                'journal_matches' => '_SYSTEMD_UNIT=ssh.service + _COMM=sshd',
                'raw_output' => 'Status for the jail: sshd',
            ]],
        ];

        $service = Mockery::mock(Fail2banService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->twice()
            ->andReturn($initialSnapshot, $refreshedSnapshot);

        $this->app->instance(Fail2banService::class, $service);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(Fail2banDashboard::class)
            ->call('refreshStatus');
    }

    public function test_selected_jail_active_bans_are_paginated(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.fail2ban.view');

        $sshdBannedIps = [];

        for ($index = 1; $index <= 11; $index++) {
            $sshdBannedIps[] = sprintf('203.0.113.%d', $index);
        }

        $service = Mockery::mock(Fail2banService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->once()
            ->andReturn([
                'enabled' => true,
                'note' => null,
                'jail_count' => 1,
                'jails' => [[
                    'name' => 'sshd',
                    'currently_failed' => 1,
                    'total_failed' => 12,
                    'currently_banned' => 11,
                    'total_banned' => 20,
                    'banned_ips' => $sshdBannedIps,
                    'log_files' => [],
                    'journal_matches' => '_SYSTEMD_UNIT=ssh.service + _COMM=sshd',
                    'raw_output' => 'Status for the jail: sshd',
                ], [
                    'name' => 'nginx-http-auth',
                    'currently_failed' => 0,
                    'total_failed' => 4,
                    'currently_banned' => 2,
                    'total_banned' => 9,
                    'banned_ips' => ['198.51.100.20', '198.51.100.21'],
                    'log_files' => ['/var/log/nginx/error.log'],
                    'journal_matches' => null,
                    'raw_output' => 'Status for the jail: nginx-http-auth',
                ]],
            ]);

        $this->app->instance(Fail2banService::class, $service);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(Fail2banDashboard::class)
            ->assertSee('Browse active bans across all jails')
            ->assertSee('sshd')
            ->assertSee('nginx-http-auth')
            ->assertSee('Journal')
            ->assertSee('Source')
            ->assertSee('Log / Match')
            ->assertDontSee('Currently Banned')
            ->assertDontSee('Current Failures')
            ->assertDontSee('Total Failures')
            ->assertSee('203.0.113.1')
            ->assertSee('203.0.113.10')
            ->call('nextPage')
            ->assertSee('203.0.113.11')
            ->assertSee('198.51.100.20')
            ->assertSee('198.51.100.21')
            ->assertSee('Log File')
            ->assertSee('/var/log/nginx/error.log')
            ->call('previousPage')
            ->assertSee('203.0.113.1');
    }

    public function test_active_ban_ip_can_be_unbanned_directly_from_its_jail(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.fail2ban.view');

        $initialSnapshot = [
            'enabled' => true,
            'note' => null,
            'jail_count' => 2,
            'jails' => [[
                'name' => 'sshd',
                'currently_failed' => 1,
                'total_failed' => 12,
                'currently_banned' => 1,
                'total_banned' => 8,
                'banned_ips' => [],
                'log_files' => [],
                'journal_matches' => '_SYSTEMD_UNIT=ssh.service + _COMM=sshd',
                'raw_output' => 'Status for the jail: sshd',
            ], [
                'name' => 'nginx-http-auth',
                'currently_failed' => 0,
                'total_failed' => 4,
                'currently_banned' => 1,
                'total_banned' => 9,
                'banned_ips' => ['198.51.100.20'],
                'log_files' => ['/var/log/nginx/error.log'],
                'journal_matches' => null,
                'raw_output' => 'Status for the jail: nginx-http-auth',
            ]],
        ];

        $refreshedSnapshot = [
            'enabled' => true,
            'note' => null,
            'jail_count' => 2,
            'jails' => [[
                'name' => 'sshd',
                'currently_failed' => 1,
                'total_failed' => 12,
                'currently_banned' => 0,
                'total_banned' => 8,
                'banned_ips' => [],
                'log_files' => [],
                'journal_matches' => '_SYSTEMD_UNIT=ssh.service + _COMM=sshd',
                'raw_output' => 'Status for the jail: sshd',
            ], [
                'name' => 'nginx-http-auth',
                'currently_failed' => 0,
                'total_failed' => 4,
                'currently_banned' => 0,
                'total_banned' => 9,
                'banned_ips' => [],
                'log_files' => ['/var/log/nginx/error.log'],
                'journal_matches' => null,
                'raw_output' => 'Status for the jail: nginx-http-auth',
            ]],
        ];

        $service = Mockery::mock(Fail2banService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->twice()
            ->andReturn($initialSnapshot, $refreshedSnapshot);
        $service->shouldReceive('unban')
            ->once()
            ->with('nginx-http-auth', '198.51.100.20');

        $this->app->instance(Fail2banService::class, $service);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(Fail2banDashboard::class)
            ->call('unbanSelectedJailIp', 'nginx-http-auth', '198.51.100.20');
    }

    private function grant(User $user, string $entitlement): void
    {
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => $entitlement,
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
    }
}
