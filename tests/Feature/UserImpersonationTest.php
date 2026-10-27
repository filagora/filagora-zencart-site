<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserImpersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_with_impersonation_access_can_act_as_regular_user_and_return(): void
    {
        $staff = User::factory()->create([
            'username' => 'staff-admin',
        ]);
        $target = User::factory()->create([
            'username' => 'target-user',
        ]);
        $this->grantEntitlement($staff, 'operator.users.act_as');

        $this->actingAs($staff)
            ->post(route('staff.impersonation.start', [
                'user' => $target,
            ]), [
                'redirect_to' => '/about',
            ])
            ->assertRedirect('/about');

        $this->assertAuthenticatedAs($target);
        $this->assertSame($staff->id, session(UserImpersonation::SESSION_ORIGINAL_USER_ID));

        $this->get('/about')
            ->assertOk()
            ->assertSee('Acting as')
            ->assertSee('target-user')
            ->assertSee('staff-admin')
            ->assertSee('action="'.route('staff.impersonation.stop').'"', false);

        $this->post(route('staff.impersonation.stop'), [
            'redirect_to' => '/about',
        ])->assertRedirect('/about');

        $this->assertAuthenticatedAs($staff);
        $this->assertNull(session(UserImpersonation::SESSION_ORIGINAL_USER_ID));
    }

    public function test_user_without_impersonation_access_cannot_act_as_another_user(): void
    {
        $staff = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('staff.impersonation.start', ['user' => $target]))
            ->assertForbidden();
    }

    public function test_standard_staff_cannot_act_as_privileged_user(): void
    {
        $staff = User::factory()->create();
        $target = User::factory()->create();
        $this->grantEntitlement($staff, 'operator.users.act_as');
        $this->grantEntitlement($target, 'operator.system.settings.manage');

        $this->actingAs($staff)
            ->post(route('staff.impersonation.start', ['user' => $target]))
            ->assertForbidden();
    }

    public function test_privileged_staff_can_act_as_privileged_user(): void
    {
        $staff = User::factory()->create();
        $target = User::factory()->create();
        $this->grantEntitlements($staff, [
            'operator.users.act_as',
            'operator.users.assign_operator_access',
        ]);
        $this->grantEntitlement($target, 'operator.system.settings.manage');

        $this->actingAs($staff)
            ->post(route('staff.impersonation.start', ['user' => $target]))
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_get_cannot_start_impersonation(): void
    {
        $staff = User::factory()->create();
        $target = User::factory()->create();
        $this->grantEntitlement($staff, 'operator.users.act_as');

        $this->actingAs($staff)
            ->get(route('staff.impersonation.start', ['user' => $target]))
            ->assertStatus(405);

        $this->assertAuthenticatedAs($staff);
        $this->assertNull(session(UserImpersonation::SESSION_ORIGINAL_USER_ID));
    }

    public function test_impersonation_redirects_ignore_scheme_relative_targets(): void
    {
        $staff = User::factory()->create([
            'username' => 'staff-admin',
        ]);
        $target = User::factory()->create([
            'username' => 'target-user',
        ]);
        $this->grantEntitlement($staff, 'operator.users.act_as');

        $this->actingAs($staff)
            ->post(route('staff.impersonation.start', [
                'user' => $target,
            ]), [
                'redirect_to' => '//evil.example/phish',
            ])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($target);

        $this->post(route('staff.impersonation.stop'), [
            'redirect_to' => '//evil.example/phish',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($staff);
    }
}
