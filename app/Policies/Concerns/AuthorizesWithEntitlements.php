<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;
use Filagora\Entitlements\Services\AuthorizationService;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * What a staff policy asks: whether the signed-in user holds an `operator.*` entitlement.
 */
trait AuthorizesWithEntitlements
{
    protected function allows(AuthUser $authUser, string $entitlement): bool
    {
        return $authUser instanceof User && app(AuthorizationService::class)->allows($authUser, $entitlement);
    }

    /**
     * @param  list<string>  $entitlements
     */
    protected function allowsAny(AuthUser $authUser, array $entitlements): bool
    {
        return $authUser instanceof User && app(AuthorizationService::class)->allowsAny($authUser, $entitlements);
    }

    /**
     * @param  list<string>  $entitlements
     */
    protected function allowsAll(AuthUser $authUser, array $entitlements): bool
    {
        return $authUser instanceof User && app(AuthorizationService::class)->allowsAll($authUser, $entitlements);
    }
}
