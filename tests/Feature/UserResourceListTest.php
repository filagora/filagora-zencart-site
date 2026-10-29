<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_username_and_operator_access_and_filters_by_ban_status(): void
    {
        $staff = $this->actingAsUserAdmin();
        $banned = User::factory()->create(['username' => 'banned_member', 'is_banned' => true]);
        $active = User::factory()->create(['username' => 'active_member']);
        UserEntitlementGrant::query()->create(['user_id' => $banned->id, 'entitlement_key' => 'operator.plugin.moderate', 'grant_type' => 'manual', 'starts_at' => now()->subMinute()]);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$staff, $banned, $active])
            ->assertTableColumnStateSet('username', 'banned_member', $banned)
            ->assertTableColumnStateSet('operator_access', 'Plugin Moderation', $banned)
            ->assertTableColumnStateSet('operator_access', 'None', $active)
            ->filterTable('is_banned', '1')
            ->assertCanSeeTableRecords([$banned])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_the_list_filters_by_verification_and_private_messages(): void
    {
        $this->actingAsUserAdmin();
        $unverified = User::factory()->create(['email_verified_at' => null, 'allow_private_messages' => false]);
        $verified = User::factory()->create(['email_verified_at' => now(), 'allow_private_messages' => true]);

        Livewire::test(ListUsers::class)
            ->filterTable('is_verified', '0')
            ->assertCanSeeTableRecords([$unverified])
            ->assertCanNotSeeTableRecords([$verified])
            ->removeTableFilter('is_verified')
            ->filterTable('allow_private_messages', '1')
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$unverified]);
    }

    private function actingAsUserAdmin(): User
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);

        foreach (['operator.dashboard.access', 'operator.entitlements.manage'] as $entitlement) {
            UserEntitlementGrant::query()->create(['user_id' => $actor->id, 'entitlement_key' => $entitlement, 'grant_type' => 'manual', 'starts_at' => now()->subMinute()]);
        }

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($actor);

        return $actor;
    }
}
