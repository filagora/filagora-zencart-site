<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserEditPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_editor_without_operator_access_rights_can_save_a_user_who_holds_privileged_grants(): void
    {
        $editor = User::factory()->create(['email_verified_at' => now()]);
        $managed = User::factory()->create(['email_verified_at' => now()]);
        $this->grant($editor, ['operator.dashboard.access', 'operator.entitlements.manage', 'operator.users.update']);
        $this->grant($managed, ['operator.system.settings.manage', 'operator.forum.moderate']);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($editor);

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])
            ->set('data.name', 'Renamed')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', $managed->fresh()->name);
        $this->assertTrue($managed->entitlementGrants()->active()->where('entitlement_key', 'operator.system.settings.manage')->exists());
        $this->assertTrue($managed->entitlementGrants()->active()->where('entitlement_key', 'operator.forum.moderate')->exists());
    }

    public function test_lifting_a_ban_clears_its_dates(): void
    {
        $editor = User::factory()->create(['email_verified_at' => now()]);
        $managed = User::factory()->create(['email_verified_at' => now(), 'is_banned' => true, 'banned_at' => now()->subDay(), 'banned_until' => now()->addDays(29)]);
        $this->grant($editor, ['operator.dashboard.access', 'operator.entitlements.manage', 'operator.users.update']);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($editor);

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])
            ->set('data.is_banned', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $managed->refresh();
        $this->assertFalse((bool) $managed->is_banned);
        $this->assertNull($managed->banned_at);
        $this->assertNull($managed->banned_until);
    }

    /**
     * @param  list<string>  $keys
     */
    private function grant(User $user, array $keys): void
    {
        foreach ($keys as $key) {
            UserEntitlementGrant::query()->create(['user_id' => $user->id, 'entitlement_key' => $key, 'grant_type' => 'manual', 'starts_at' => now()->subMinute()]);
        }
    }
}
