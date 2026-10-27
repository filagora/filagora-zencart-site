<?php

namespace Tests\Feature;

use App\Filament\Pages\NftablesDashboard;
use App\Models\User;
use App\Services\NftablesService;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NftablesDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_super_admin_can_view_nftables_dashboard(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.nftables.view');

        $service = Mockery::mock(NftablesService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->once()
            ->andReturn([
                'enabled' => true,
                'note' => 'Wrapper command is in use.',
                'table_count' => 1,
                'chain_count' => 1,
                'rule_count' => 1,
                'set_count' => 1,
                'sets' => [[
                    'key' => 'inet:filter:blocked_ipv4',
                    'family' => 'inet',
                    'table' => 'filter',
                    'name' => 'blocked_ipv4',
                    'type' => 'ipv4_addr',
                    'flags' => ['timeout'],
                    'element_count' => 2,
                    'elements' => [[
                        'key' => 'inet:filter:blocked_ipv4:0:203.0.113.10',
                        'value' => '203.0.113.10',
                        'removable' => true,
                    ]],
                ]],
            ]);

        $this->app->instance(NftablesService::class, $service);

        $this->actingAs($user)
            ->get('/dashboard/nftables')
            ->assertOk()
            ->assertSee('Nftables')
            ->assertSee('blocked_ipv4')
            ->assertSee('203.0.113.10')
            ->assertSee('Wrapper command is in use.')
            ->assertSee('Inspect the active nftables ruleset');
    }

    public function test_forum_moderator_is_forbidden_from_nftables_dashboard(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.forum.moderate');

        $this->actingAs($user)
            ->get('/dashboard/nftables')
            ->assertForbidden();
    }

    public function test_regular_user_is_redirected_from_nftables_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard/nftables')
            ->assertRedirect(route('home'));
    }

    public function test_set_elements_are_paginated(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.nftables.view');

        $elements = [];

        for ($index = 1; $index <= 11; $index++) {
            $value = sprintf('203.0.113.%d', $index);
            $elements[] = [
                'key' => "inet:filter:blocked_ipv4:{$index}:{$value}",
                'value' => $value,
                'removable' => true,
            ];
        }

        $service = Mockery::mock(NftablesService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->once()
            ->andReturn([
                'enabled' => true,
                'note' => null,
                'table_count' => 1,
                'chain_count' => 1,
                'rule_count' => 2,
                'set_count' => 2,
                'sets' => [[
                    'key' => 'inet:filter:blocked_ipv4',
                    'family' => 'inet',
                    'table' => 'filter',
                    'name' => 'blocked_ipv4',
                    'type' => 'ipv4_addr',
                    'flags' => ['timeout'],
                    'element_count' => 11,
                    'elements' => $elements,
                ], [
                    'key' => 'inet:filter:blocked_ipv6',
                    'family' => 'inet',
                    'table' => 'filter',
                    'name' => 'blocked_ipv6',
                    'type' => 'ipv6_addr',
                    'flags' => [],
                    'element_count' => 1,
                    'elements' => [[
                        'key' => 'inet:filter:blocked_ipv6:0:2001:db8::10',
                        'value' => '2001:db8::10',
                        'removable' => true,
                    ]],
                ]],
            ]);

        $this->app->instance(NftablesService::class, $service);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(NftablesDashboard::class)
            ->assertSee('Browse nftables set elements across all visible tables')
            ->assertSee('blocked_ipv4')
            ->assertSee('203.0.113.1')
            ->assertSee('203.0.113.10')
            ->call('nextPage')
            ->assertSee('203.0.113.11')
            ->assertSee('2001:db8::10')
            ->call('previousPage')
            ->assertSee('203.0.113.1');
    }

    public function test_set_element_can_be_removed_directly_from_dashboard(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.nftables.view');

        $initialSnapshot = [
            'enabled' => true,
            'note' => null,
            'table_count' => 1,
            'chain_count' => 1,
            'rule_count' => 1,
            'set_count' => 1,
            'sets' => [[
                'key' => 'inet:filter:blocked_ipv4',
                'family' => 'inet',
                'table' => 'filter',
                'name' => 'blocked_ipv4',
                'type' => 'ipv4_addr',
                'flags' => ['timeout'],
                'element_count' => 1,
                'elements' => [[
                    'key' => 'inet:filter:blocked_ipv4:0:203.0.113.10',
                    'value' => '203.0.113.10',
                    'removable' => true,
                ]],
            ]],
        ];

        $refreshedSnapshot = [
            'enabled' => true,
            'note' => null,
            'table_count' => 1,
            'chain_count' => 1,
            'rule_count' => 1,
            'set_count' => 1,
            'sets' => [[
                'key' => 'inet:filter:blocked_ipv4',
                'family' => 'inet',
                'table' => 'filter',
                'name' => 'blocked_ipv4',
                'type' => 'ipv4_addr',
                'flags' => ['timeout'],
                'element_count' => 0,
                'elements' => [],
            ]],
        ];

        $service = Mockery::mock(NftablesService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->twice()
            ->andReturn($initialSnapshot, $refreshedSnapshot);
        $service->shouldReceive('removeElement')
            ->once()
            ->with('inet', 'filter', 'blocked_ipv4', '203.0.113.10');

        $this->app->instance(NftablesService::class, $service);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(NftablesDashboard::class)
            ->call('removeSetElement', 'inet', 'filter', 'blocked_ipv4', '203.0.113.10');
    }

    public function test_quick_filters_can_scope_set_elements_by_table_and_set(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.nftables.view');

        $service = Mockery::mock(NftablesService::class);
        $service->shouldReceive('getStatusSnapshot')
            ->once()
            ->andReturn([
                'enabled' => true,
                'note' => null,
                'table_count' => 2,
                'tables' => ['inet/filter', 'ip/filter'],
                'chain_count' => 2,
                'chains' => ['inet/filter/input', 'ip/filter/input'],
                'rule_count' => 2,
                'set_count' => 2,
                'sets' => [[
                    'key' => 'inet:filter:blocked_ipv4',
                    'family' => 'inet',
                    'table' => 'filter',
                    'name' => 'blocked_ipv4',
                    'type' => 'ipv4_addr',
                    'flags' => ['timeout'],
                    'element_count' => 1,
                    'elements' => [[
                        'key' => 'inet:filter:blocked_ipv4:0:203.0.113.10',
                        'value' => '203.0.113.10',
                        'removable' => true,
                    ]],
                ], [
                    'key' => 'ip:filter:rate_limiters',
                    'family' => 'ip',
                    'table' => 'filter',
                    'name' => 'rate_limiters',
                    'type' => 'ipv4_addr',
                    'flags' => [],
                    'element_count' => 1,
                    'elements' => [[
                        'key' => 'ip:filter:rate_limiters:0:198.51.100.25',
                        'value' => '198.51.100.25',
                        'removable' => true,
                    ]],
                ]],
            ]);

        $this->app->instance(NftablesService::class, $service);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(NftablesDashboard::class)
            ->assertSee('Quick Filters')
            ->assertSee('inet/filter')
            ->assertSee('ip/filter')
            ->assertSee('203.0.113.10')
            ->assertSee('198.51.100.25')
            ->call('applyTableFilter', 'inet/filter')
            ->assertSee('203.0.113.10')
            ->assertDontSee('198.51.100.25')
            ->call('applySetFilter', 'inet:filter:blocked_ipv4')
            ->assertSee('203.0.113.10')
            ->assertDontSee('198.51.100.25')
            ->call('clearQuickFilters')
            ->assertSee('203.0.113.10')
            ->assertSee('198.51.100.25');
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
