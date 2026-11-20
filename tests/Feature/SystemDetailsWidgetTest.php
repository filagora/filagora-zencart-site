<?php

namespace Tests\Feature;

use App\Filament\Widgets\SystemDetailsWidget;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SystemDetailsWidgetTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_widget_shows_the_search_engine_in_use(): void
    {
        config(['filagora-search.engine' => 'meilisearch']);

        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'operator.system.settings.manage',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
        $this->actingAs($user);

        Livewire::test(SystemDetailsWidget::class)
            ->assertOk()
            ->assertSee('Search engine meilisearch')
            ->assertDontSee('Scout');
    }
}
