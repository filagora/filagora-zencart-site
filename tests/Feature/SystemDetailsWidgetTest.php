<?php

namespace Tests\Feature;

use App\Filament\Widgets\SystemDetailsWidget;
use App\Models\User;
use Composer\InstalledVersions;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SystemDetailsWidgetTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_widget_renders_when_an_optional_package_is_not_installed(): void
    {
        $this->assertFalse(InstalledVersions::isInstalled('laravel/scout'));

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
            ->assertSee('Scout not installed');
    }
}
