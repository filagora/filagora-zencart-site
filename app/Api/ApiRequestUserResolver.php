<?php

namespace App\Api;

use App\Models\User;
use App\Support\ApiResponseMessage;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who is calling an API route that is public but takes a key: the key's owner when a bearer token
 * is sent (an unknown key is nobody), otherwise the signed-in member if there is one. A request
 * with a key is held to the ability the route asks for and to the entitlement behind it.
 */
class ApiRequestUserResolver
{
    public function __construct(
        private readonly ApiScopeCatalog $scopes,
    ) {}

    public function hasBearerToken(Request $request): bool
    {
        return trim((string) $request->bearerToken()) !== '';
    }

    public function resolve(Request $request): ?User
    {
        $token = trim((string) $request->bearerToken());

        if ($token !== '') {
            $accessToken = PersonalAccessToken::findToken($token);
            $owner = $accessToken?->tokenable;

            if (! $accessToken instanceof PersonalAccessToken || ! $owner instanceof User) {
                return null;
            }

            $accessToken->forceFill(['last_used_at' => now()])->save();

            return $owner->withAccessToken($accessToken);
        }

        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    public function ensureAbilityForBearerToken(Request $request, ?User $user, string $ability): void
    {
        if (! $this->hasBearerToken($request)) {
            return;
        }

        abort_if(! $user instanceof User, 401, ApiResponseMessage::unauthenticated());
        abort_if(! $user->tokenCan($ability), 403, ApiResponseMessage::tokenAbilityRequired());
        abort_if(! $this->scopes->userHasEntitlementForAbility($user, $ability), 403, ApiResponseMessage::tokenEntitlementRequired());
    }
}
