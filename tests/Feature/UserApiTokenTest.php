<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class UserApiTokenTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_user_can_create_personal_access_token(): void
    {
        $user = User::factory()->create();

        $newToken = $user->createToken(
            name: 'default',
            abilities: ['forums:read'],
            expiresAt: now()->addDay(),
        );

        [$tokenId, $plainTextSecret] = explode('|', $newToken->plainTextToken, 2);

        $storedToken = PersonalAccessToken::query()->findOrFail($tokenId);

        $this->assertSame($user->id, $storedToken->tokenable_id);
        $this->assertSame(User::class, $storedToken->tokenable_type);
        $this->assertSame('default', $storedToken->name);
        $this->assertSame(['forums:read'], $storedToken->abilities);
        $this->assertNotNull($storedToken->expires_at);
        $this->assertNotSame($plainTextSecret, $storedToken->token);
    }

    public function test_personal_access_token_authenticates_api_request(): void
    {
        $user = User::factory()->create([
            'name' => 'API Reader',
            'username' => 'api-reader',
            'email' => 'api-reader@example.com',
            'email_verified_at' => now(),
            'membership_expires_at' => now()->addDay(),
            'stripe_id' => 'cus_sensitive',
            'pm_type' => 'visa',
            'pm_last_four' => '4242',
            'timezone' => 'America/New_York',
            'allow_private_messages' => false,
            'is_banned' => true,
            'ban_reason' => 'sensitive moderation note',
        ]);
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.user.read',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
        $token = $user->createToken('default', ['user:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', 'api-reader')
            ->assertJsonPath('data.name', 'API Reader')
            ->assertJsonPath('data.email', 'api-reader@example.com')
            ->assertJsonPath('data.email_verified_at', $user->email_verified_at?->toISOString())
            ->assertJsonPath('data.avatar_url', $user->avatar_url)
            ->assertJsonMissingPath('data.stripe_id')
            ->assertJsonMissingPath('data.pm_type')
            ->assertJsonMissingPath('data.pm_last_four')
            ->assertJsonMissingPath('data.membership_expires_at')
            ->assertJsonMissingPath('data.timezone')
            ->assertJsonMissingPath('data.allow_private_messages')
            ->assertJsonMissingPath('data.is_banned')
            ->assertJsonMissingPath('data.ban_reason');
    }

    public function test_personal_access_token_requires_matching_api_entitlement(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('default', ['user:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJson([
                'message' => __('api.auth.token_entitlement_required'),
            ]);
    }
}
