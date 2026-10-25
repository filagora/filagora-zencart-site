<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Core\Settings\SettingsRegistry;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_response_redirects_to_safe_internal_intended_path(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        session(['url.intended' => '/profile']);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(url('/profile'), $response->getTargetUrl());
        $this->assertNull(session('url.intended'));
    }

    public function test_login_response_redirects_to_absolute_same_origin_intended_url(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // Illuminate\Routing\Redirector::guest() (the standard Laravel
        // auth-redirect-to-login flow) always stores an absolute URL here,
        // not a root-relative path.
        session(['url.intended' => url('/dashboard/privacy-requests/8/edit')]);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(url('/dashboard/privacy-requests/8/edit'), $response->getTargetUrl());
        $this->assertNull(session('url.intended'));
    }

    public function test_login_response_rejects_protocol_relative_intended_path(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        session(['url.intended' => '//evil.example']);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(Filament::getUrl(), $response->getTargetUrl());
        $this->assertNull(session('url.intended'));
    }

    public function test_login_response_rejects_absolute_intended_url(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        session(['url.intended' => 'https://evil.example/path']);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(Filament::getUrl(), $response->getTargetUrl());
        $this->assertNull(session('url.intended'));
    }

    public function test_unverified_user_is_redirected_to_verification_flow_with_intended_path(): void
    {
        $user = User::factory()->unverified()->make();
        $this->actingAs($user);

        session(['url.intended' => '/profile']);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(route('filament.dashboard.auth.email-verification.prompt', ['modal' => 1]), $response->getTargetUrl());
        $this->assertSame('/profile', session('url.intended'));
    }

    public function test_unverified_user_without_intended_is_redirected_to_verification_prompt(): void
    {
        $user = User::factory()->unverified()->make();
        $this->actingAs($user);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(route('filament.dashboard.auth.email-verification.prompt', ['modal' => 1]), $response->getTargetUrl());
        $this->assertNull(session('url.intended'));
    }

    public function test_verified_user_without_active_policy_acknowledgement_is_redirected_to_privacy_tab(): void
    {
        $this->ensureActivePolicy();

        $user = User::factory()->withoutPrivacyPolicyAcknowledgement()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        session(['url.intended' => '/dashboard']);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(route('profile.show', ['tab' => 'privacy']), $response->getTargetUrl());
        $this->assertSame('/dashboard', session('privacy_policy.intended'));
        $this->assertTrue((bool) session('privacy_ack_required'));
        $this->assertNull(session('url.intended'));
    }

    public function test_verified_user_with_active_policy_acknowledgement_is_redirected_to_intended(): void
    {
        $policy = $this->ensureActivePolicy();

        $user = User::factory()->withoutPrivacyPolicyAcknowledgement()->create([
            'email_verified_at' => now(),
        ]);
        UserPrivacyPolicyAcceptance::query()->create([
            'user_id' => $user->id,
            'privacy_policy_version_id' => $policy->id,
            'accepted_at' => now(),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'phpunit'),
        ]);

        $this->actingAs($user);
        session(['url.intended' => '/dashboard']);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(url('/dashboard'), $response->getTargetUrl());
        $this->assertNull(session('privacy_policy.intended'));
        $this->assertNull(session('url.intended'));
    }

    public function test_verified_user_without_acknowledgement_is_not_redirected_when_gdpr_enforcement_is_disabled(): void
    {
        $this->ensureActivePolicy();
        app(SettingsRegistry::class)->set('enforce_gdpr', false);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        session(['url.intended' => '/dashboard']);

        $response = app(LoginResponseContract::class)->toResponse(Request::create('/dashboard/login', 'POST'));

        $this->assertSame(url('/dashboard'), $response->getTargetUrl());
        $this->assertNull(session('privacy_policy.intended'));
        $this->assertNull(session('url.intended'));
    }

    private function ensureActivePolicy(): PrivacyPolicyVersion
    {
        return PrivacyPolicyVersion::query()->create([
            'version' => 'test-login-policy',
            'label' => 'Login test policy',
            'summary' => 'Login flow policy',
            'is_active' => true,
            'published_at' => now(),
        ]);
    }
}
