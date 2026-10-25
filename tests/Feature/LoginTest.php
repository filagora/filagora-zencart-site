<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Log In');
    }

    public function test_already_authenticated_user_is_redirected_away_from_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_login_form_does_not_render_twice_on_the_standalone_login_page(): void
    {
        // site_header (included on every page, including /login itself) also
        // carries a hidden copy of this same form for the header-triggered
        // modal. Without excluding it on /login, the page would render two
        // <input id="login-username"> elements plus two forms both named
        // "username"/"password" - a real bug caught after Phase 1 shipped.
        $html = $this->get(route('login'))->getContent();

        $this->assertIsString($html);
        $this->assertSame(1, substr_count($html, 'id="login-username"'));
        $this->assertSame(1, substr_count($html, 'id="home-auth-modal"'));
    }

    public function test_login_form_register_and_forgot_password_links_switch_panels_within_the_modal(): void
    {
        // Regression: these used to be plain links, so clicking "Register" or
        // "Forgot password?" from inside the modal dropped the visitor onto a
        // real full-page navigation instead of switching panels in place.
        $html = $this->get(route('login'))->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('data-auth-modal-url="/register"', $html);
        $this->assertStringContainsString('data-auth-modal-url="/password/forgot"', $html);
    }

    public function test_login_page_does_not_reopen_a_pointless_empty_modal_over_itself(): void
    {
        User::factory()->create([
            'username' => 'standard-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                '_auth_form' => 'login',
                'username' => 'standard-user',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username']);

        // The errors/old-input flashed above are still in the session for
        // this next request, exactly as they would be for the real
        // redirect-back.
        $html = $this->get(route('login'))->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString('data-auth-modal-open-on-load', $html);
    }

    public function test_login_failure_elsewhere_on_the_site_reopens_the_modal_on_redirect_back(): void
    {
        User::factory()->create([
            'username' => 'standard-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->from('/forums')
            ->post(route('login.store'), [
                '_auth_form' => 'login',
                'username' => 'standard-user',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/forums')
            ->assertSessionHasErrors(['username']);

        $html = $this->get('/forums')->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('data-auth-modal-open-on-load="login"', $html);
    }

    public function test_valid_credentials_log_the_user_in_and_redirect_home(): void
    {
        $user = User::factory()->create([
            'username' => 'standard-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login.store'), [
            'username' => 'standard-user',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_fails_with_a_validation_error(): void
    {
        User::factory()->create([
            'username' => 'standard-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login.store'), [
            'username' => 'standard-user',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['username']);
        $this->assertGuest();
    }

    public function test_banned_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'username' => 'banned-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
        $user->forceFill(['is_banned' => true, 'banned_until' => now()->addDay()])->save();

        $response = $this->post(route('login.store'), [
            'username' => 'banned-user',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['username']);
        $this->assertGuest();
    }

    public function test_two_factor_enabled_user_is_sent_to_the_code_step_without_password_required_again(): void
    {
        $user = $this->makeTwoFactorEnabledUser();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Authentication code', false);
    }

    public function test_two_factor_step_completes_login_with_a_valid_code(): void
    {
        $user = $this->makeTwoFactorEnabledUser();
        $secret = (string) $user->getTwoFactorSecret();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response = $this->post(route('login.two-factor.store'), [
            'otp' => $this->currentValidOtp($secret),
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_step_rejects_an_invalid_code(): void
    {
        $user = $this->makeTwoFactorEnabledUser();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response = $this->post(route('login.two-factor.store'), [
            'otp' => '000000',
        ]);

        $response->assertSessionHasErrors(['otp']);
        $this->assertGuest();
    }

    public function test_two_factor_step_rejects_a_request_with_no_pending_login(): void
    {
        $this->post(route('login.two-factor.store'), ['otp' => '123456'])
            ->assertStatus(419);
    }

    public function test_cancelling_the_two_factor_step_clears_the_pending_login(): void
    {
        $user = $this->makeTwoFactorEnabledUser();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $this->post(route('login.two-factor.cancel'))->assertRedirect(route('login'));

        $this->post(route('login.two-factor.store'), ['otp' => '123456'])
            ->assertStatus(419);
    }

    private function makeTwoFactorEnabledUser(): User
    {
        $user = User::factory()->create([
            'username' => 'twofactor-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $user->enableTwoFactor(app(Google2FA::class)->generateSecretKey(32));
        $user->save();

        return $user;
    }

    private function currentValidOtp(string $secret): string
    {
        $google2fa = app(Google2FA::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $otp = $google2fa->getCurrentOtp($secret);

            if ($google2fa->verifyKey($secret, $otp, 2)) {
                return $otp;
            }

            usleep(300000);
        }

        return $google2fa->getCurrentOtp($secret);
    }
}
