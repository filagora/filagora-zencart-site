<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class UserResourceImpersonationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_with_act_as_access_can_start_impersonation_from_the_edit_page(): void
    {
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $target = User::factory()->create(['email_verified_at' => now()]);
        $this->grantEntitlement($staff, 'operator.dashboard.access');
        $this->grantEntitlement($staff, 'operator.entitlements.manage');
        $this->grantEntitlement($staff, 'operator.users.act_as');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertActionVisible('actAsUser')
            ->callAction('actAsUser')
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_act_as_button_is_hidden_without_act_as_access(): void
    {
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $target = User::factory()->create(['email_verified_at' => now()]);
        $this->grantEntitlement($staff, 'operator.dashboard.access');
        $this->grantEntitlement($staff, 'operator.entitlements.manage');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertActionHidden('actAsUser');
    }
}
