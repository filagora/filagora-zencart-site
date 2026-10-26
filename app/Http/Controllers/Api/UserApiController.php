<?php

namespace App\Http\Controllers\Api;

use App\Api\ApiRequestUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserSelfResource;
use App\Models\User;
use App\Support\ApiResponseMessage;
use Illuminate\Http\Request;

class UserApiController extends Controller
{
    public function __construct(private readonly ApiRequestUserResolver $callers) {}

    public function show(Request $request): UserSelfResource
    {
        $user = $this->callers->resolve($request);

        $this->callers->ensureAbilityForBearerToken($request, $user, 'user:read');

        abort_if(! $user instanceof User, 401, ApiResponseMessage::unauthenticated());

        return new UserSelfResource($user);
    }
}
