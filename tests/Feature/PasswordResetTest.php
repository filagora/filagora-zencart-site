<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot Password');
    }

    public function test_forgot_password_form_does_not_render_twice_on_the_standalone_page(): void
    {
        // Same class of bug as login/register: site_header's hidden modal
        // copy of this form must be excluded on the page that already
        // renders it as real content.
        $html = $this->get(route('password.request'))->getContent();

        $this->assertIsString($html);
        $this->assertSame(1, substr_count($html, 'id="forgot-password-email"'));
    }

    public function test_forgot_password_form_back_to_log_in_link_switches_panels_within_the_modal(): void
    {
        $html = $this->get(route('password.request'))->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('data-auth-modal-url="/login"', $html);
    }

    public function test_requesting_a_reset_link_for_a_known_email_sends_a_notification_linking_to_the_native_reset_page(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'known@example.test']);

        $this->post(route('password.email'), ['email' => 'known@example.test'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            // Illuminate\Auth\Notifications\ResetPassword's default resetUrl()
            // falls back to route('password.reset', [...]) with zero
            // customization needed - this is exactly why that route is named
            // "password.reset", matching Laravel's own convention.
            $expectedPath = route('password.reset', ['token' => $notification->token, 'email' => $user->email], false);

            return str_contains($mail->actionUrl, $expectedPath);
        });
    }

    public function test_requesting_a_reset_link_for_an_unknown_email_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@example.test']);

        // Same session-status flash either way; no validation error
        // distinguishing "no such account" from "link sent".
        $response->assertSessionHas('status');
        $response->assertSessionDoesntHaveErrors();
        Notification::assertNothingSent();
    }

    public function test_reset_form_renders_with_the_token_and_email_prefilled(): void
    {
        $this->get(route('password.reset', ['token' => 'a-token', 'email' => 'someone@example.test']))
            ->assertOk()
            ->assertSee('Reset Password')
            ->assertSee('value="a-token"', false)
            ->assertSee('value="someone@example.test"', false);
    }

    public function test_valid_token_resets_the_password_and_redirects_to_login(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-me@example.test',
            'password' => Hash::make('old-password123'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset-me@example.test',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_invalid_token_fails_with_a_validation_error(): void
    {
        $user = User::factory()->create([
            'email' => 'bad-token@example.test',
            'password' => Hash::make('old-password123'),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => 'not-the-real-token',
            'email' => 'bad-token@example.test',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_mismatched_password_confirmation_fails_with_a_validation_error(): void
    {
        $user = User::factory()->create(['email' => 'mismatch@example.test']);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'mismatch@example.test',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }
}
