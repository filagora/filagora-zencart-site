<?php

namespace App\Api;

use App\Models\User;
use Filagora\Entitlements\Services\EntitlementResolver;

/**
 * The abilities an API key can carry and the entitlement a member needs before they may give a
 * key that ability.
 */
class ApiScopeCatalog
{
    public function __construct(private readonly EntitlementResolver $entitlements) {}

    /**
     * @return array<string, array{entitlement: string, label: string, description: string}>
     */
    public function definitions(): array
    {
        return [
            'cms:read' => [
                'entitlement' => 'api.cms.read',
                'label' => 'CMS Read',
                'description' => 'Read CMS pages, blocks, and navigation over the API.',
            ],
            'user:read' => [
                'entitlement' => 'api.user.read',
                'label' => 'User Read',
                'description' => 'Read the authenticated user profile payload over the API.',
            ],
            'plugins:read' => [
                'entitlement' => 'api.plugins.read',
                'label' => 'Plugins Read',
                'description' => 'Read plugin catalog, detail, release, and update-check API endpoints.',
            ],
            'plugins:download' => [
                'entitlement' => 'api.plugins.download',
                'label' => 'Plugins Download',
                'description' => 'Receive plugin download metadata and download packages when otherwise allowed.',
            ],
            'plugins:write' => [
                'entitlement' => 'api.plugins.write',
                'label' => 'Plugins Write',
                'description' => 'Use plugin subscription, submission, and moderation mutation endpoints.',
            ],
        ];
    }

    public function entitlementForAbility(string $ability): ?string
    {
        return $this->definitions()[trim($ability)]['entitlement'] ?? null;
    }

    public function userHasEntitlementForAbility(User $user, string $ability): bool
    {
        $required = $this->entitlementForAbility($ability);

        return is_string($required) && $required !== '' && $this->entitlements->hasEntitlement($user, $required);
    }

    /**
     * @return list<array{ability: string, entitlement: string, label: string, description: string}>
     */
    public function selfServiceScopesFor(User $user): array
    {
        $scopes = [];

        foreach ($this->definitions() as $ability => $definition) {
            if ($this->userHasEntitlementForAbility($user, $ability)) {
                $scopes[] = ['ability' => $ability, ...$definition];
            }
        }

        return $scopes;
    }

    /**
     * @return list<string>
     */
    public function selfServiceAbilitiesFor(User $user): array
    {
        return array_column($this->selfServiceScopesFor($user), 'ability');
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    public function labelsForAbilities(array $abilities): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(fn (mixed $ability): string => trim((string) $ability), $abilities))));

        if (in_array('*', $normalized, true)) {
            return ['Full API access (legacy wildcard)'];
        }

        $definitions = $this->definitions();

        return array_map(fn (string $ability): string => $definitions[$ability]['label'] ?? $ability, $normalized);
    }
}
