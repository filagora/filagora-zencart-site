<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceBanDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_ban_on_with_a_duration_preset_fills_in_the_dates(): void
    {
        $managed = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAsUserAdmin();

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])
            ->set('data.is_banned', true)
            ->set('data.ban_duration', '7d')
            ->set('data.ban_reason', 'Spam account')
            ->call('save')
            ->assertHasNoFormErrors();

        $managed->refresh();
        $this->assertTrue((bool) $managed->is_banned);
        $this->assertNotNull($managed->banned_at);
        $this->assertNotNull($managed->banned_until);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $managed->banned_until->timestamp, 120);
    }

    public function test_permanent_preset_clears_the_end_date(): void
    {
        $managed = User::factory()->create([
            'email_verified_at' => now(),
            'is_banned' => true,
            'banned_at' => now()->subDay(),
            'banned_until' => now()->addDays(30),
        ]);
        $this->actingAsUserAdmin();

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])
            ->set('data.ban_duration', 'permanent')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($managed->fresh()->banned_until);
        $this->assertTrue((bool) $managed->fresh()->is_banned);
    }

    private function actingAsUserAdmin(): void
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);
        foreach (['operator.dashboard.access', 'operator.entitlements.manage', 'operator.users.view', 'operator.users.update'] as $entitlement) {
            UserEntitlementGrant::query()->create([
                'user_id' => $actor->id,
                'entitlement_key' => $entitlement,
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        }
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($actor);
    }
}
