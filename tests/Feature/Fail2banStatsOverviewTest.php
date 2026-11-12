<?php

namespace Tests\Feature;

use App\Filament\Widgets\Fail2banStatsOverview;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class Fail2banStatsOverviewTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_fail2ban_stats_widget_renders_cards_with_expected_labels(): void
    {
        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'operator.system.fail2ban.view',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
        $this->actingAs($user);

        Livewire::test(Fail2banStatsOverview::class, [
            'jailCount' => 4,
            'currentlyBanned' => 2,
            'totalFailedAttempts' => 19,
        ])
            ->assertSee(__('filament.pages.fail2ban.stats.jails'))
            ->assertSee(__('filament.pages.fail2ban.stats.currently_banned'))
            ->assertSee(__('filament.pages.fail2ban.stats.total_failed'))
            ->assertSee('4')
            ->assertSee('2')
            ->assertSee('19');
    }
}
