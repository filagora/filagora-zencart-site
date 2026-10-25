<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_renders(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Register');
    }

    public function test_already_verified_authenticated_user_is_redirected_away_from_the_register_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('home'));
    }

    public function test_unverified_authenticated_user_can_reach_the_register_page_and_is_logged_out(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('register'));

        $response->assertOk();
        $response->assertSee('Register');
        $this->assertGuest();
    }

    public function test_register_form_log_in_link_switches_panels_within_the_modal(): void
    {
        $html = $this->get(route('register'))->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('data-auth-modal-url="/login"', $html);
    }

    public function test_valid_submission_creates_and_logs_in_the_user_and_redirects_home(): void
    {
        $response = $this->post(route('register.store'), [
            '_auth_form' => 'register',
            'username' => 'new-user',
            'name' => 'New User',
            'email' => 'new-user@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'accept_privacy_policy' => true,
        ]);

        $user = User::query()->where('username', 'new-user')->first();

        $this->assertNotNull($user);
        // Not route('home'): the new account isn't email-verified yet, so
        // AuthRedirectResolver correctly sends it to the native verification
        // prompt instead of the fallback.
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('new-user@example.test', $user->email);
        $this->assertSame('New User', $user->name);
    }

    public function test_valid_submission_grants_the_can_receive_emails_entitlement(): void
    {
        $this->post(route('register.store'), [
            '_auth_form' => 'register',
            'username' => 'entitlement-user',
            'name' => 'Entitlement User',
            'email' => 'entitlement-user@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'accept_privacy_policy' => true,
        ]);

        $user = User::query()->where('username', 'entitlement-user')->firstOrFail();

        $this->assertTrue(
            UserEntitlementGrant::query()
                ->where('user_id', $user->id)
                ->where('entitlement_key', 'notifications.email.enabled')
                ->exists()
        );
    }

    public function test_duplicate_username_fails_with_a_validation_error(): void
    {
        User::factory()->create(['username' => 'taken-username']);

        $response = $this->post(route('register.store'), [
            '_auth_form' => 'register',
            'username' => 'taken-username',
            'name' => 'Someone Else',
            'email' => 'someone-else@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['username']);
        $this->assertGuest();
    }

    public function test_mismatched_password_confirmation_fails_with_a_validation_error(): void
    {
        $response = $this->post(route('register.store'), [
            '_auth_form' => 'register',
            'username' => 'mismatch-user',
            'name' => 'Mismatch User',
            'email' => 'mismatch-user@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    public function test_submission_without_privacy_policy_acknowledgement_fails_when_a_policy_is_active(): void
    {
        PrivacyPolicyVersion::query()->create([
            'version' => 'test-1',
            'label' => 'Test policy',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $response = $this->post(route('register.store'), [
            '_auth_form' => 'register',
            'username' => 'no-privacy-ack-user',
            'name' => 'No Privacy Ack',
            'email' => 'no-privacy-ack@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['accept_privacy_policy']);
        $this->assertGuest();
    }

    public function test_register_form_does_not_render_twice_on_the_standalone_register_page(): void
    {
        $html = $this->get(route('register'))->getContent();

        $this->assertIsString($html);
        $this->assertSame(1, substr_count($html, 'id="register-username"'));
    }
}
