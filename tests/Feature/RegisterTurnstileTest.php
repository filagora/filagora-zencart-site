<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
            'services.turnstile.enforce_in_non_production' => true,
            'registration.email_allowlist.enabled' => false,
            'spam.enabled' => false,
        ]);
    }

    public function test_turnstile_is_hidden_in_non_production_when_explicit_enforcement_is_disabled(): void
    {
        config([
            'services.turnstile.enforce_in_non_production' => false,
        ]);

        Livewire::test(Register::class)
            ->assertDontSee('This site is protected by Cloudflare Turnstile.')
            ->assertDontSee('data-turnstile-state-path="data.turnstile_token"', false);
    }

    public function test_registration_shows_turnstile_validation_error_and_resets_widget_when_token_is_missing(): void
    {
        Livewire::test(Register::class)
            ->set('data.username', 'turnstile_missing_user')
            ->set('data.name', 'Turnstile Missing')
            ->set('data.email', 'turnstile-missing@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasErrors(['data.turnstile_token'])
            ->assertDispatched('turnstile:reset', statePath: 'data.turnstile_token')
            ->assertSee(__('auth.errors.turnstile_required'));
    }

    public function test_registration_succeeds_when_turnstile_verification_passes(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

        Livewire::test(Register::class)
            ->set('data.username', 'turnstile_verified_user')
            ->set('data.name', 'Turnstile Verified')
            ->set('data.email', 'turnstile-verified@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.turnstile_token', 'valid-turnstile-token')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice', ['modal' => 1]));
    }
}
