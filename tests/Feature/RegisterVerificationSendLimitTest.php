<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Pages\Auth\Register;
use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterVerificationSendLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_blocked_once_the_daily_limit_is_reached(): void
    {
        Notification::fake();
        $this->enableLimit(dailyMax: 0);

        Livewire::test(Register::class)
            ->set('data.username', 'blocked_user')
            ->set('data.name', 'Blocked User')
            ->set('data.email', 'blocked@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->call('register')
            ->assertHasErrors(['data.email']);

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);

        Notification::assertNothingSent();
    }

    public function test_registration_succeeds_and_sends_verification_when_under_the_daily_limit(): void
    {
        Notification::fake();
        $this->enableLimit(dailyMax: 1);

        Livewire::test(Register::class)
            ->set('data.username', 'first_user')
            ->set('data.name', 'First User')
            ->set('data.email', 'first@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'first@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_is_not_limited_when_the_limit_is_disabled(): void
    {
        Notification::fake();
        $this->enableLimit(dailyMax: 0, enabled: false);

        Livewire::test(Register::class)
            ->set('data.username', 'unlimited_user')
            ->set('data.name', 'Unlimited User')
            ->set('data.email', 'unlimited@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'unlimited@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_is_blocked_once_the_daily_limit_is_reached(): void
    {
        Notification::fake();
        $this->enableLimit(dailyMax: 0);

        $user = User::factory()->unverified()->create();

        Livewire::actingAs($user)
            ->test(EmailVerificationPrompt::class)
            ->callAction('resendNotification');

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    private function enableLimit(int $dailyMax, bool $enabled = true): void
    {
        config([
            'registration.verification_send_limit.enabled' => $enabled,
            'registration.verification_send_limit.daily_max' => $dailyMax,
        ]);
    }
}
