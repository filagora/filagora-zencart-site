<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Compliance\Models\CookieConsentCookie;
use Filagora\Compliance\Models\CookieConsentGroup;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Models\UserCookieConsent;
use Filagora\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class CookieConsentPersistenceTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        CookiePolicyVersion::query()->create(['version' => '2026.03.1', 'label' => 'March 2026', 'is_active' => true, 'published_at' => now()->subDay()]);

        foreach ([
            ['essential', true, ['laravel_session', 'XSRF-TOKEN']],
            ['analytics', false, ['_ga']],
            ['marketing', false, ['_fbp']],
        ] as $order => [$key, $required, $cookies]) {
            $group = CookieConsentGroup::query()->create(['group_key' => $key, 'label' => ucfirst($key), 'is_required' => $required, 'default_enabled' => $required, 'is_enabled' => true, 'sort_order' => $order]);

            foreach ($cookies as $position => $cookie) {
                CookieConsentCookie::query()->create(['group_id' => $group->id, 'cookie_name' => $cookie, 'label' => $cookie, 'is_enabled' => true, 'sort_order' => $position]);
            }
        }
    }

    public function test_authenticated_user_can_store_cookie_consent_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/profile/cookie-consent', [
            'groups' => [
                'essential' => false,
                'analytics' => true,
                'marketing' => false,
            ],
            'cookies' => [
                'laravel_session' => false,
                'XSRF-TOKEN' => false,
                '_ga' => true,
                '_fbp' => false,
            ],
            'updatedAt' => now()->subMinutes(5)->toIso8601String(),
        ]);

        $response->assertOk()
            ->assertJsonPath('consent.necessary', true)
            ->assertJsonPath('consent.groups.essential', true)
            ->assertJsonPath('consent.cookies.laravel_session', true)
            ->assertJsonPath('consent.cookies.XSRF-TOKEN', true)
            ->assertJsonPath('consent.cookies._ga', true)
            ->assertJsonPath('consent.cookies._fbp', false)
            ->assertJsonPath('policyVersion', '2026.03.1')
            ->assertJsonPath('activePolicyVersion', '2026.03.1')
            ->assertJsonPath('requiresReconsent', false);

        $consent = UserCookieConsent::query()
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($consent);
        $this->assertNotNull($consent?->ip_hash);
        $this->assertNotNull($consent?->user_agent_hash);
        $this->assertSame(true, $consent?->consent['groups']['essential'] ?? null);
    }

    public function test_authenticated_user_can_fetch_stored_cookie_consent_preferences(): void
    {
        $user = User::factory()->create();
        $policyVersion = CookiePolicyVersion::query()->where('version', '2026.03.1')->firstOrFail();

        UserCookieConsent::query()->create([
            'user_id' => $user->id,
            'cookie_policy_version_id' => $policyVersion->id,
            'consent' => [
                'necessary' => true,
                'groups' => [
                    'essential' => true,
                    'analytics' => false,
                    'marketing' => false,
                ],
                'cookies' => [
                    'laravel_session' => true,
                    'XSRF-TOKEN' => true,
                    '_ga' => false,
                    '_fbp' => false,
                ],
                'updatedAt' => now()->subDay()->toIso8601String(),
            ],
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'test-agent'),
            'consented_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->getJson('/profile/cookie-consent');

        $response->assertOk()
            ->assertJsonPath('consent.necessary', true)
            ->assertJsonPath('consent.groups.analytics', false)
            ->assertJsonPath('consent.cookies._ga', false)
            ->assertJsonPath('policyVersion', '2026.03.1')
            ->assertJsonPath('activePolicyVersion', '2026.03.1')
            ->assertJsonPath('requiresReconsent', false)
            ->assertJsonPath('consentedAt', $response->json('consentedAt'));
    }

    public function test_authenticated_user_must_reconsent_when_active_cookie_policy_changes(): void
    {
        $user = User::factory()->create();
        $oldPolicyVersion = CookiePolicyVersion::query()->where('version', '2026.03.1')->firstOrFail();

        $oldPolicyVersion->update(['is_active' => false]);

        $newPolicyVersion = CookiePolicyVersion::query()->create([
            'version' => '2026.04.1',
            'label' => 'Updated cookie policy',
            'notes' => 'Changed tracking disclosures.',
            'is_active' => true,
            'published_at' => now(),
        ]);

        UserCookieConsent::query()->create([
            'user_id' => $user->id,
            'cookie_policy_version_id' => $oldPolicyVersion->id,
            'consent' => [
                'necessary' => true,
                'groups' => ['essential' => true, 'analytics' => false, 'marketing' => false],
                'cookies' => ['laravel_session' => true, 'XSRF-TOKEN' => true, '_ga' => false, '_fbp' => false],
                'updatedAt' => now()->subDay()->toIso8601String(),
            ],
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'test-agent'),
            'consented_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->getJson('/profile/cookie-consent')
            ->assertOk()
            ->assertJsonPath('consent', null)
            ->assertJsonPath('policyVersion', '2026.03.1')
            ->assertJsonPath('activePolicyVersion', $newPolicyVersion->version)
            ->assertJsonPath('requiresReconsent', true);
    }

    public function test_guest_cannot_store_cookie_consent_preferences(): void
    {
        $this->postJson('/profile/cookie-consent', [
            'groups' => ['analytics' => true],
            'cookies' => ['_ga' => true],
        ])->assertUnauthorized();
    }

    public function test_cookie_consent_endpoints_return_not_found_when_workflow_is_disabled(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'enable_cookie_consent_workflow'],
            [
                'label' => 'Enable Cookie Consent Workflow',
                'value' => '0',
                'value_type' => 'boolean',
                'is_public' => false,
            ]
        );

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/profile/cookie-consent')
            ->assertNotFound();

        $this->actingAs($user)
            ->postJson('/profile/cookie-consent', [
                'groups' => ['analytics' => true],
                'cookies' => ['_ga' => true],
            ])
            ->assertNotFound();
    }

    public function test_cookie_consent_ui_is_hidden_when_workflow_is_disabled(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'enable_cookie_consent_workflow'],
            [
                'label' => 'Enable Cookie Consent Workflow',
                'value' => '0',
                'value_type' => 'boolean',
                'is_public' => false,
            ]
        );

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-cookie-banner', false);
        $response->assertDontSee('data-cookie-modal', false);
        $response->assertDontSee('data-cookie-open-settings', false);
    }
}
