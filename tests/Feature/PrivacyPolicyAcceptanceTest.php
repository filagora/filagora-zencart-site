<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PrivacyPolicyAcceptanceTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        PrivacyPolicyVersion::query()->create(['version' => '2026.03.1', 'label' => 'March 2026', 'is_active' => true, 'published_at' => now()->subDay()]);
    }

    public function test_authenticated_user_can_acknowledge_active_privacy_policy(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile/privacy-policy/accept');

        $response->assertRedirect('/profile?tab=privacy');

        $policy = PrivacyPolicyVersion::query()->where('version', '2026.03.1')->firstOrFail();
        $acceptance = UserPrivacyPolicyAcceptance::query()
            ->where('user_id', $user->id)
            ->where('privacy_policy_version_id', $policy->id)
            ->first();

        $this->assertNotNull($acceptance);
        $this->assertNotNull($acceptance?->accepted_at);
        $this->assertNotNull($acceptance?->ip_hash);
        $this->assertNotNull($acceptance?->user_agent_hash);
    }

    public function test_profile_privacy_tab_renders_policy_and_transparency_summary(): void
    {
        $user = User::factory()->withoutPrivacyPolicyAcknowledgement()->create();

        $response = $this->actingAs($user)->get('/profile?tab=privacy');

        $response->assertOk();
        $response->assertSee('Data Use Summary');
        $response->assertSee('Email preferences');
        $response->assertSee('optional email notification preferences');
        $response->assertSee('Privacy Policy Acknowledgement');
        $response->assertSee('Acknowledge Active Privacy Policy');
        $response->assertSee('2026.03.1');
    }

    public function test_user_with_outstanding_policy_is_redirected_from_protected_routes_to_privacy_tab(): void
    {
        $user = User::factory()->withoutPrivacyPolicyAcknowledgement()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/plugins/submit');

        $response->assertRedirect('/profile?tab=privacy');
        $this->assertSame('/plugins/submit', session('privacy_policy.intended'));
        $this->assertTrue((bool) session('privacy_ack_required'));
    }

    public function test_user_with_outstanding_policy_can_access_privacy_tab_but_not_other_profile_tabs(): void
    {
        $user = User::factory()->withoutPrivacyPolicyAcknowledgement()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/profile?tab=privacy')
            ->assertOk();

        $this->actingAs($user)
            ->get('/profile?tab=details')
            ->assertRedirect('/profile?tab=privacy');
    }

    public function test_outstanding_policy_does_not_block_when_gdpr_enforcement_is_disabled(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'enforce_gdpr'],
            [
                'label' => 'Enforce GDPR',
                'value' => '0',
                'value_type' => 'boolean',
                'is_public' => false,
            ]
        );

        $user = User::factory()->withoutPrivacyPolicyAcknowledgement()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/plugins/submit')
            ->assertOk();
    }

    public function test_acknowledge_button_is_hidden_when_active_policy_is_already_acknowledged(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile?tab=privacy');

        $response->assertOk();
        $response->assertSee('You already acknowledged the active privacy policy.');
        $response->assertDontSee('Acknowledge Active Privacy Policy');
    }

    public function test_guest_cannot_acknowledge_privacy_policy(): void
    {
        $response = $this->post('/profile/privacy-policy/accept');

        $response->assertRedirect('/?auth=login&intended=%2Fprofile');
    }

    public function test_acknowledge_redirects_to_saved_intended_path_when_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['privacy_policy.intended' => '/dashboard']);

        $response = $this->post('/profile/privacy-policy/accept');

        $response->assertRedirect('/dashboard');
        $this->assertNull(session('privacy_policy.intended'));
    }

    public function test_acknowledge_does_not_redirect_to_cookie_consent_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['privacy_policy.intended' => '/profile/cookie-consent']);

        $response = $this->post('/profile/privacy-policy/accept');

        $response->assertRedirect('/profile?tab=privacy');
        $this->assertNull(session('privacy_policy.intended'));
    }

    public function test_profile_privacy_tab_signposts_required_acknowledgement_section(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['privacy_ack_required' => true])
            ->get('/profile?tab=privacy');

        $response->assertOk();
        $response->assertSee('Action required: acknowledge the active privacy policy.');
        $response->assertSee('id="privacy-policy-acknowledgement"', false);
        $response->assertSee('Jump to acknowledgement');
    }
}
