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

class RegisterEmailAllowlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_create_user_or_send_verification_email_for_unapproved_address(): void
    {
        Notification::fake();
        $this->enableAllowlist(domains: ['approved.example']);

        Livewire::test(Register::class)
            ->set('data.username', 'denied_user')
            ->set('data.name', 'Denied User')
            ->set('data.email', 'denied@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->call('register')
            ->assertHasErrors(['data.email']);

        $this->assertDatabaseMissing('users', [
            'email' => 'denied@example.com',
        ]);

        Notification::assertNothingSent();
    }

    public function test_registration_sends_verification_email_for_approved_domain(): void
    {
        Notification::fake();
        $this->enableAllowlist(domains: ['example.com']);

        Livewire::test(Register::class)
            ->set('data.username', 'approved_domain_user')
            ->set('data.name', 'Approved Domain')
            ->set('data.email', 'approved@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'approved@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_sends_verification_email_for_approved_exact_address(): void
    {
        Notification::fake();
        $this->enableAllowlist(addresses: ['invited@example.org']);

        Livewire::test(Register::class)
            ->set('data.username', 'approved_email_user')
            ->set('data.name', 'Approved Email')
            ->set('data.email', 'Invited@Example.Org')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'Invited@Example.Org')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_does_not_send_to_unapproved_existing_user(): void
    {
        Notification::fake();
        $this->enableAllowlist(domains: ['approved.example']);

        $user = User::factory()->unverified()->create([
            'email' => 'unapproved@example.com',
        ]);

        Livewire::actingAs($user)
            ->test(EmailVerificationPrompt::class)
            ->callAction('resendNotification');

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_sends_email_immediately_for_approved_existing_user(): void
    {
        Notification::fake();
        $this->enableAllowlist(domains: ['example.com']);

        $user = User::factory()->unverified()->create([
            'email' => 'approved@example.com',
        ]);

        Livewire::actingAs($user)
            ->test(EmailVerificationPrompt::class)
            ->callAction('resendNotification');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * @param  list<string>  $addresses
     * @param  list<string>  $domains
     */
    private function enableAllowlist(array $addresses = [], array $domains = []): void
    {
        config([
            'registration.email_allowlist.enabled' => true,
            'registration.email_allowlist.addresses' => $addresses,
            'registration.email_allowlist.domains' => $domains,
        ]);
    }
}
