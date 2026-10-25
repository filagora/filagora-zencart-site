<?php

namespace Tests;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Pagination\Paginator;
use Tests\Support\CanonicalOperatorEntitlements;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Livewire's pagination hook swaps Paginator::$defaultView to its own view whenever a
        // paginated component renders and only restores it when that component is destroyed, so a
        // full-page GET of one leaves the static default changed for the rest of the process and a
        // later plain-Blade ->links() renders no ?page= links. Reset it before every test.
        Paginator::useTailwind();
    }

    protected function grantEntitlement(User $user, string $entitlement, string $reason = 'Granted in test fixture.'): void
    {
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => $entitlement,
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  list<string>  $entitlements
     */
    protected function grantEntitlements(User $user, array $entitlements, string $reason = 'Granted in test fixture.'): void
    {
        foreach ($entitlements as $entitlement) {
            $this->grantEntitlement($user, $entitlement, $reason);
        }
    }

    protected function grantCanonicalOperatorEntitlements(User $user, string $role): void
    {
        $this->grantEntitlements(
            $user,
            CanonicalOperatorEntitlements::forRole($role),
            sprintf('Granted in test fixture for canonical %s operator access.', $role)
        );
    }

    /**
     * A forum moderator: the canonical entitlements of the `forum_moderator` role.
     */
    protected function makeForumModerator(User $user): User
    {
        $this->grantCanonicalOperatorEntitlements($user, 'forum_moderator');

        return $user;
    }
}
