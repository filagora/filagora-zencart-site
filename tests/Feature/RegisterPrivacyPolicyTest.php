<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Register;
use App\Models\User;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Notifications\Services\Preferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterPrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_privacy_policy_acknowledgement_when_active_policy_exists(): void
    {
        $this->ensureActivePolicy();

        Livewire::test(Register::class)
            ->set('data.username', 'privacy_required_user')
            ->set('data.name', 'Privacy Required')
            ->set('data.email', 'privacy-required@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', false)
            ->call('register')
            ->assertHasErrors(['data.accept_privacy_policy']);
    }

    public function test_registration_privacy_policy_link_uses_noopener_noreferrer(): void
    {
        $this->ensureActivePolicy();

        Livewire::test(Register::class)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_registration_records_active_policy_acceptance(): void
    {
        $policy = $this->ensureActivePolicy();

        Livewire::test(Register::class)
            ->set('data.username', 'privacy_accepted_user')
            ->set('data.name', 'Privacy Accepted')
            ->set('data.email', 'privacy-accepted@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'privacy-accepted@example.com')->firstOrFail();

        $acceptance = UserPrivacyPolicyAcceptance::query()
            ->where('user_id', $user->id)
            ->where('privacy_policy_version_id', $policy->id)
            ->first();

        $this->assertNotNull($acceptance);
        $this->assertNotNull($acceptance?->accepted_at);
    }

    public function test_registration_does_not_require_acknowledgement_when_gdpr_enforcement_is_disabled(): void
    {
        $this->ensureActivePolicy();
        app(SettingsRegistry::class)->set('enforce_gdpr', false);

        Livewire::test(Register::class)
            ->set('data.username', 'privacy_not_required_user')
            ->set('data.name', 'Privacy Not Required')
            ->set('data.email', 'privacy-not-required@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', false)
            ->call('register')
            ->assertHasNoErrors();
    }

    public function test_registration_marks_new_user_for_first_post_moderation_when_setting_is_enabled(): void
    {
        app(SettingsRegistry::class)->set('moderate_first_post_for_new_users', true);

        Livewire::test(Register::class)
            ->set('data.username', 'first_post_moderated_user')
            ->set('data.name', 'First Post Moderated')
            ->set('data.email', 'first-post-moderated@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'first-post-moderated@example.com')->firstOrFail();

        $this->assertTrue($user->moderate_first_forum_post);
        $this->assertFalse($user->always_moderate_forum_content);
    }

    public function test_registration_grants_the_can_receive_emails_entitlement(): void
    {
        $this->ensureActivePolicy();

        Livewire::test(Register::class)
            ->set('data.username', 'grant_on_register_user')
            ->set('data.name', 'Grant On Register')
            ->set('data.email', 'grant-on-register@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'grant-on-register@example.com')->firstOrFail();

        $grant = UserEntitlementGrant::query()
            ->where('user_id', $user->id)
            ->where('entitlement_key', 'notifications.email.enabled')
            ->first();

        $this->assertNotNull($grant);
        $this->assertNull($grant->revoked_at);
        $this->assertFalse(app(Preferences::class)->isSuppressed($user));
    }

    private function ensureActivePolicy(): PrivacyPolicyVersion
    {
        return PrivacyPolicyVersion::query()->create([
            'version' => 'test-register-policy',
            'label' => 'Register test policy',
            'summary' => 'Register flow policy',
            'is_active' => true,
            'published_at' => now(),
        ]);
    }
}
