<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginTwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_otp_for_two_factor_enabled_user(): void
    {
        $user = $this->makeTwoFactorEnabledUser();

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->assertHasErrors(['data.otp']);

        $this->assertGuest();
    }

    public function test_otp_input_is_hidden_for_non_two_factor_accounts(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'no-otp-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        Livewire::test(Login::class)
            ->assertDontSee(__('auth.labels.authentication_code'))
            ->set('data.username', $user->username)
            ->assertDontSee(__('auth.labels.authentication_code'));
    }

    public function test_a_username_or_wrong_password_does_not_show_that_an_account_has_two_factor(): void
    {
        $user = $this->makeTwoFactorEnabledUser();

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->assertDontSee(__('auth.labels.authentication_code'))
            ->set('data.password', 'not-the-password')
            ->call('authenticate')
            ->assertHasErrors(['data.username'])
            ->assertDontSee(__('auth.labels.authentication_code'));
    }

    public function test_otp_input_is_shown_once_the_password_is_accepted(): void
    {
        $user = $this->makeTwoFactorEnabledUser();

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->assertSee(__('auth.labels.authentication_code'));

        $this->assertGuest();
    }

    public function test_a_code_cannot_be_used_without_the_password(): void
    {
        $user = $this->makeTwoFactorEnabledUser();

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->set('data.password', 'not-the-password')
            ->set('data.otp', $this->currentValidOtp((string) $user->getTwoFactorSecret()))
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest();
    }

    public function test_login_rejects_invalid_otp_for_two_factor_enabled_user(): void
    {
        $user = $this->makeTwoFactorEnabledUser();
        $secret = $user->getTwoFactorSecret();
        $validOtp = $this->currentValidOtp((string) $secret);
        $invalidOtp = str_pad((string) ((((int) $validOtp) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->set('data.otp', $invalidOtp)
            ->call('authenticate')
            ->assertHasErrors(['data.otp']);

        $this->assertGuest();
    }

    public function test_login_succeeds_with_valid_otp_for_two_factor_enabled_user(): void
    {
        $user = $this->makeTwoFactorEnabledUser();
        $secret = $user->getTwoFactorSecret();
        $validOtp = $this->currentValidOtp((string) $secret);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->set('data.otp', $validOtp)
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_succeeds_with_valid_recovery_code_and_consumes_it(): void
    {
        $user = $this->makeTwoFactorEnabledUser();
        $codes = $user->generateRecoveryCodes();
        $user->save();
        $code = $codes[0];

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->set('data.otp', $code)
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(7, $user->fresh()->recoveryCodesRemainingCount());
    }

    public function test_login_rejects_an_already_used_recovery_code(): void
    {
        $user = $this->makeTwoFactorEnabledUser();
        $codes = $user->generateRecoveryCodes();
        $user->save();
        $code = $codes[0];

        $this->assertTrue($user->redeemRecoveryCode($code));
        $user->save();

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->set('data.otp', $code)
            ->call('authenticate')
            ->assertHasErrors(['data.otp']);

        $this->assertGuest();
    }

    public function test_login_succeeds_without_otp_when_two_factor_is_disabled(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'standard-user',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password123')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    private function makeTwoFactorEnabledUser(): User
    {
        $user = User::factory()->staff()->create([
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
