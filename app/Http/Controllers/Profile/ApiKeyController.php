<?php

namespace App\Http\Controllers\Profile;

use App\Api\ApiScopeCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A member's API keys: making one with the abilities their entitlements allow, and revoking it.
 * The key itself is shown once, right after it is made.
 */
class ApiKeyController extends Controller
{
    public function __construct(private readonly ApiScopeCatalog $scopes) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validateWithBag('apiToken', [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in($this->scopes->selfServiceAbilitiesFor($user))],
        ]);

        $token = $user->createToken(trim((string) $validated['name']), array_values(array_unique(array_map(fn (mixed $ability): string => trim((string) $ability), $validated['abilities']))));

        return redirect()->route('profile.show', ['tab' => 'api-keys'])
            ->with('profile_status', __('profile.flash.api_key_created'))
            ->with('new_api_token', $token->plainTextToken);
    }

    public function revoke(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        $user = $this->member($request);

        abort_unless((int) $token->tokenable_id === (int) $user->id && $token->tokenable_type === $user->getMorphClass(), 404);

        $token->delete();

        return redirect()->route('profile.show', ['tab' => 'api-keys'])->with('profile_status', __('profile.flash.api_key_revoked'));
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
