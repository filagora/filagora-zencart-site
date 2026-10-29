<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserEditHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_privileged_editor_can_assign_and_remove_super_admin(): void
    {
        $managed = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAsPrivilegedEditor();

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])->set('data.is_super_admin', true)->call('save');
        $this->assertTrue($managed->fresh()->hasRole('super_admin'));

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])->set('data.is_super_admin', false)->call('save');
        $this->assertFalse($managed->fresh()->hasRole('super_admin'));
    }

    public function test_a_super_admin_holds_every_entitlement(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->assertTrue(app(AuthorizationService::class)->allows($admin, 'operator.system.settings.manage'));
        $this->assertFalse(app(AuthorizationService::class)->allows($other, 'operator.system.settings.manage'));
    }

    public function test_nobody_can_change_their_own_operator_access_or_super_admin_role(): void
    {
        $editor = $this->actingAsPrivilegedEditor();
        $editor->assignRole('super_admin');

        Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
            ->set('data.is_super_admin', false)
            ->set('data.operator_entitlements', [])
            ->call('save');

        $editor->refresh();
        $this->assertTrue($editor->hasRole('super_admin'));
        $this->assertTrue($editor->entitlementGrants()->active()->where('entitlement_key', 'operator.users.assign_operator_access')->exists());
    }

    public function test_granting_again_after_a_revocation_keeps_the_earlier_grant_on_record(): void
    {
        $managed = User::factory()->create(['email_verified_at' => now()]);
        $first = $this->actingAsPrivilegedEditor();

        $save = fn (array $keys) => Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])->set('data.operator_entitlements', $keys)->call('save');

        $save(['operator.forum.moderate']);
        $save([]);
        $save(['operator.forum.moderate']);

        $grants = UserEntitlementGrant::query()->where('user_id', $managed->id)->where('entitlement_key', 'operator.forum.moderate')->orderBy('id')->get();

        $this->assertCount(2, $grants);
        $this->assertNotNull($grants[0]->revoked_at);
        $this->assertSame($first->id, $grants[0]->granted_by_id);
        $this->assertNotNull($grants[0]->revoked_by_id);
        $this->assertNull($grants[1]->revoked_at);
    }

    public function test_changing_an_email_unverifies_the_account_and_sends_a_confirmation(): void
    {
        Notification::fake();
        $managed = User::factory()->create(['email' => 'old@example.test', 'email_verified_at' => now()]);
        $this->actingAsPrivilegedEditor();

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])
            ->set('data.email', 'new@example.test')
            ->call('save')
            ->assertHasNoFormErrors();

        $managed->refresh();
        $this->assertSame('new@example.test', $managed->email);
        $this->assertNull($managed->email_verified_at);
        Notification::assertSentTo($managed, VerifyEmail::class);
    }

    public function test_saving_without_changing_the_email_keeps_the_account_verified(): void
    {
        Notification::fake();
        $managed = User::factory()->create(['email_verified_at' => now()->subDay()]);
        $this->actingAsPrivilegedEditor();

        Livewire::test(EditUser::class, ['record' => $managed->getRouteKey()])->set('data.name', 'Renamed')->call('save');

        $this->assertNotNull($managed->fresh()->email_verified_at);
        Notification::assertNotSentTo($managed, VerifyEmail::class);
    }

    private function actingAsPrivilegedEditor(): User
    {
        $editor = User::factory()->create(['email_verified_at' => now()]);

        foreach (['operator.dashboard.access', 'operator.entitlements.manage', 'operator.users.view', 'operator.users.update', 'operator.users.assign_operator_access'] as $key) {
            UserEntitlementGrant::query()->create(['user_id' => $editor->id, 'entitlement_key' => $key, 'grant_type' => 'manual', 'starts_at' => now()->subMinute()]);
        }

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($editor);

        return $editor;
    }
}
