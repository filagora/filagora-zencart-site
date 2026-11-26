<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Jobs\SendPasswordResetLink;
use App\Models\User;
use Carbon\CarbonInterval;
use Filament\Auth\Notifications\ResetPassword as DashboardResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
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

    public function test_a_reset_request_only_queues_the_work_whether_or_not_the_account_exists(): void
    {
        Queue::fake();
        Notification::fake();
        User::factory()->create(['email' => 'queued@example.test']);

        foreach (['queued@example.test', 'nobody@example.test'] as $email) {
            $this->post(route('password.email'), ['email' => $email]);

            Queue::assertPushed(SendPasswordResetLink::class, fn (SendPasswordResetLink $job): bool => $job->email === $email);
        }

        // Anything done during the request for a real account only would show in the response time.
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_changing_the_user_agent_does_not_reset_the_request_limit(): void
    {
        Queue::fake();

        foreach (range(1, 6) as $attempt) {
            $this->withHeader('User-Agent', 'browser-'.$attempt)
                ->post(route('password.email'), ['email' => 'nobody@example.test'])
                ->assertRedirect();
        }

        $this->withHeader('User-Agent', 'browser-7')
            ->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertTooManyRequests();

        // Another visitor is unaffected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertRedirect();
    }

    public function test_known_unknown_and_repeated_requests_all_get_the_same_reply(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'known@example.test']);

        // The second request for the known address is inside the broker's own throttle.
        foreach (['known@example.test', 'known@example.test', 'nobody@example.test'] as $email) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertSessionHas('status', __('auth.messages.password_reset_requested'))
                ->assertSessionDoesntHaveErrors();
        }
    }

    public function test_a_refused_reset_email_is_reported_and_gets_the_same_reply(): void
    {
        Exceptions::fake();
        $this->useFaiveloMailer();
        Http::fake(['faivelo.test/*' => Http::response(['error' => 'This message was refused by our outbound content screening.'], 422)]);
        User::factory()->create(['email' => 'refused@example.test']);

        $this->post(route('password.email'), ['email' => 'refused@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', __('auth.messages.password_reset_requested'));

        Exceptions::assertReported(TransportException::class);
    }

    public function test_an_unreachable_mail_provider_is_reported_and_gets_the_same_reply(): void
    {
        Exceptions::fake();
        $this->useFaiveloMailer();
        Http::fake(['faivelo.test/*' => Http::failedConnection()]);
        User::factory()->create(['email' => 'timeout@example.test']);

        $this->post(route('password.email'), ['email' => 'timeout@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', __('auth.messages.password_reset_requested'));

        Exceptions::assertReported(ConnectionException::class);
    }

    public function test_a_send_with_an_unknown_outcome_keeps_the_emailed_link_valid(): void
    {
        Exceptions::fake();
        $this->useFaiveloMailer();
        // A provider that accepts the message and answers too late looks the same as this.
        Http::fake(['faivelo.test/*' => Http::failedConnection()]);
        User::factory()->create(['email' => 'slow@example.test']);

        $this->post(route('password.email'), ['email' => 'slow@example.test']);

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_a_failed_send_can_be_retried_after_the_broker_throttle(): void
    {
        Exceptions::fake();
        $this->useFaiveloMailer();
        Http::fake(['faivelo.test/*' => Http::sequence()
            ->push(['error' => 'This message was refused by our outbound content screening.'], 422)
            ->push(['success' => true], 200)]);
        User::factory()->create(['email' => 'retry@example.test']);

        $this->post(route('password.email'), ['email' => 'retry@example.test']);
        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => 'retry@example.test']);

        Http::assertSentCount(2);
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

    public function test_an_unknown_email_fails_the_reset_step_the_same_way_as_a_bad_token(): void
    {
        User::factory()->create(['email' => 'exists@example.test']);

        foreach (['exists@example.test', 'nobody@example.test'] as $email) {
            $this->post(route('password.update'), [
                'token' => 'not-the-real-token',
                'email' => $email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertSessionHasErrors(['email' => __('passwords.token')]);
        }
    }

    public function test_a_failed_reset_step_is_padded_past_the_cost_of_checking_a_token(): void
    {
        $user = User::factory()->create(['email' => 'pending@example.test']);
        Password::createToken($user);
        Sleep::fake();

        // Only the first address has a token to hash against, which takes hundreds of
        // milliseconds at production cost. Both replies must wait well beyond that.
        foreach (['pending@example.test', 'nobody@example.test'] as $email) {
            $this->post(route('password.update'), [
                'token' => 'not-the-real-token',
                'email' => $email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertSessionHasErrors('email');
        }

        Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds > 700, 2);
    }

    public function test_the_dashboard_reset_request_only_queues_the_work_whether_or_not_the_account_exists(): void
    {
        Queue::fake();
        Notification::fake();
        Filament::setCurrentPanel('dashboard');
        User::factory()->create(['email' => 'staff-queued@example.test']);

        foreach (['staff-queued@example.test', 'nobody@example.test'] as $email) {
            Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $email])->call('request');

            Queue::assertPushed(
                SendPasswordResetLink::class,
                fn (SendPasswordResetLink $job): bool => $job->email === $email && $job->panelId === 'dashboard',
            );
        }

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_the_dashboard_reset_email_links_to_the_signed_dashboard_page(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('dashboard');
        $user = User::factory()->create(['email' => 'staff-link@example.test']);

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $user->email])->call('request');

        Notification::assertSentTo($user, DashboardResetPassword::class, function (DashboardResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/dashboard/password-reset/reset')
                && URL::hasValidSignature(Request::create($url));
        });
    }

    public function test_the_dashboard_reset_request_page_is_the_one_that_hides_unknown_accounts(): void
    {
        $this->get(Filament::getPanel('dashboard')->getRequestPasswordResetUrl())
            ->assertOk()
            ->assertSeeLivewire(RequestPasswordReset::class);
    }

    public function test_the_dashboard_reset_request_gives_known_unknown_and_repeated_requests_the_same_reply(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('dashboard');

        $user = User::factory()->create(['email' => 'staff@example.test']);

        // The second request for the known address is inside the broker's own throttle.
        foreach (['staff@example.test', 'staff@example.test', 'nobody@example.test'] as $email) {
            // The page's own per-visitor rate limit is not what is under test.
            Cache::flush();

            Livewire::test(RequestPasswordReset::class)
                ->fillForm(['email' => $email])
                ->call('request')
                ->assertNotified(
                    FilamentNotification::make()
                        ->title(__('auth.messages.password_reset_requested'))
                        ->success(),
                )
                ->assertSchemaStateSet(['email' => null]);
        }

        Notification::assertSentToTimes($user, DashboardResetPassword::class, 1);
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

    private function useFaiveloMailer(): void
    {
        config()->set('mail.default', 'faivelo');
        config()->set('services.faivelo.key', 'fvl_live_test');
        config()->set('services.faivelo.endpoint', 'https://faivelo.test/api/v1/emails');
    }
}
