<?php

namespace Database\Factories;

use App\Models\User;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $activePolicy = PrivacyPolicyVersion::query()
                ->where('is_active', true)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();

            if ($activePolicy instanceof PrivacyPolicyVersion) {
                UserPrivacyPolicyAcceptance::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'privacy_policy_version_id' => $activePolicy->id,
                    ],
                    [
                        'accepted_at' => now(),
                        'ip_hash' => hash('sha256', '127.0.0.1'),
                        'user_agent_hash' => hash('sha256', 'phpunit'),
                    ]
                );
            }

            // Every real user gets this at registration (or the one-time backfill), so a plain
            // factory-made user behaves like a normal user. Tests of a suppressed or revoked user
            // use withoutCanReceiveEmails().
            UserEntitlementGrant::query()->create([
                'user_id' => $user->id,
                'entitlement_key' => (string) config('filagora-zencart.notifications.email_entitlement', 'notifications.email.enabled'),
                'grant_type' => 'registration',
                'source_type' => 'registration',
                'starts_at' => now(),
            ]);
        });
    }

    /**
     * A user who may open the dashboard, as `User::canAccessPanel()` requires.
     */
    public function staff(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserEntitlementGrant::query()->create([
                'user_id' => $user->id,
                'entitlement_key' => 'operator.dashboard.access',
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withoutPrivacyPolicyAcknowledgement(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserPrivacyPolicyAcceptance::query()
                ->where('user_id', $user->id)
                ->delete();
        });
    }

    /**
     * Revoke the email-notification entitlement configure() grants by default, for tests of a user
     * whose optional notification mail is suppressed.
     */
    public function withoutCanReceiveEmails(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserEntitlementGrant::query()
                ->where('user_id', $user->id)
                ->where('entitlement_key', (string) config('filagora-zencart.notifications.email_entitlement', 'notifications.email.enabled'))
                ->update(['revoked_at' => now()]);
        });
    }
}
