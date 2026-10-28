<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserEditPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_edit_page_hides_entitlement_summary_section_at_top_of_form(): void
    {
        $actor = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $managedUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        foreach ([
            'operator.dashboard.access',
            'operator.users.view',
            'operator.users.update',
            'operator.users.assign_operator_access',
            'operator.entitlements.manage',
        ] as $entitlement) {
            UserEntitlementGrant::query()->create([
                'user_id' => $actor->id,
                'entitlement_key' => $entitlement,
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        }

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($actor);

        Livewire::test(EditUser::class, ['record' => $managedUser->getRouteKey()])
            ->assertDontSee('Effective entitlement grants for this user, including the source of each access path.')
            ->assertDontSee('No effective entitlements are currently assigned.')
            ->assertSee('Direct Operator Access');
    }

    public function test_user_edit_saves_markdown_signature_format(): void
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $managedUser = User::factory()->create([
            'email_verified_at' => now(),
            'signature' => '**Existing signature**',
            'signature_format' => 'markdown',
        ]);

        foreach ([
            'operator.dashboard.access',
            'operator.users.view',
            'operator.users.update',
            'operator.users.assign_operator_access',
            'operator.entitlements.manage',
        ] as $entitlement) {
            UserEntitlementGrant::query()->create([
                'user_id' => $actor->id,
                'entitlement_key' => $entitlement,
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        }

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($actor);

        Livewire::test(EditUser::class, ['record' => $managedUser->getRouteKey()])
            ->assertSet('data.signature', '**Existing signature**')
            ->set('data.signature', '**Updated signature**')
            ->call('save')
            ->assertHasNoFormErrors();

        $managedUser->refresh();
        $this->assertSame('**Updated signature**', $managedUser->signature);
        $this->assertSame('markdown', $managedUser->signature_format);
    }
}
