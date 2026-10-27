<?php

namespace Tests\Feature;

use App\Filament\Widgets\NftablesStatsOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NftablesStatsOverviewTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_nftables_stats_widget_renders_cards_with_expected_labels(): void
    {
        Livewire::test(NftablesStatsOverview::class, [
            'tableCount' => 2,
            'tables' => ['inet/filter', 'ip/filter'],
            'chainCount' => 4,
            'chains' => ['inet/filter/input', 'inet/filter/forward', 'inet/filter/output', 'ip/filter/input'],
            'ruleCount' => 12,
            'setCount' => 3,
            'sets' => [
                ['name' => 'blocked_ipv4', 'element_count' => 2, 'family' => 'inet', 'table' => 'filter', 'type' => 'ipv4_addr'],
                ['name' => 'blocked_ipv6', 'element_count' => 1, 'family' => 'inet', 'table' => 'filter', 'type' => 'ipv6_addr'],
                ['name' => 'rate_limiters', 'element_count' => 0, 'family' => 'inet', 'table' => 'filter', 'type' => 'ipv4_addr'],
            ],
        ])
            ->assertSee(__('filament.pages.nftables.stats.tables'))
            ->assertSee(__('filament.pages.nftables.stats.chains'))
            ->assertSee(__('filament.pages.nftables.stats.rules'))
            ->assertSee(__('filament.pages.nftables.stats.sets'))
            ->assertSee('2')
            ->assertSee('4')
            ->assertSee('12')
            ->assertSee('3')
            ->assertSee('inet/filter, ip/filter')
            ->assertSee('inet/filter/input, inet/filter/forward, inet/filter/output, +1 more')
            ->assertSee('blocked_ipv4, blocked_ipv6, rate_limiters');
    }
}
