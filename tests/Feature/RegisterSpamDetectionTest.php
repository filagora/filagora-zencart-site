<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Register;
use App\Models\User;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterSpamDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'spam.enabled' => true,
            'spam.actions.reject_spam_registrations' => true,
            'spam.actions.moderate_suspicious_registrations' => true,
            'spam.stop_forum_spam.enabled' => true,
            'spam.stop_forum_spam.url' => 'https://www.stopforumspam.com/api',
            'spam.temporary_email.enabled' => false,
            'spam.project_honeypot.enabled' => false,
            'registration.email_allowlist.enabled' => false,
        ]);

        app(SettingsRegistry::class)->set('moderate_first_post_for_new_users', false);
    }

    public function test_registration_is_rejected_when_stop_forum_spam_flags_the_submission(): void
    {
        Http::fake([
            'https://www.stopforumspam.com/api*' => Http::response([
                'success' => 1,
                'username' => ['frequency' => 2],
                'ip' => ['frequency' => 0, 'confidence' => 0],
                'email' => ['frequency' => 0, 'confidence' => 0],
            ]),
        ]);

        Livewire::test(Register::class)
            ->set('data.username', 'blocked_user')
            ->set('data.name', 'Blocked User')
            ->set('data.email', 'blocked@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasErrors(['data.email']);

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_registration_marks_suspicious_user_for_always_moderated_posting(): void
    {
        config([
            'spam.stop_forum_spam.enabled' => false,
            'spam.temporary_email.enabled' => true,
            'spam.temporary_email.url' => 'https://api.usechecker.com/v1/check',
            'spam.temporary_email.api_key' => 'test-key',
        ]);

        Http::fake([
            'https://api.usechecker.com/v1/check*' => Http::response([
                'is_temporary' => true,
                'provider' => 'mailinator',
            ]),
        ]);

        Livewire::test(Register::class)
            ->set('data.username', 'suspicious_user')
            ->set('data.name', 'Suspicious User')
            ->set('data.email', 'suspicious@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'suspicious@example.com')->firstOrFail();

        $this->assertTrue($user->always_moderate_forum_content);
        $this->assertFalse($user->moderate_first_forum_post);
    }

    public function test_registration_allows_clean_submission_without_moderation_flags(): void
    {
        Http::fake([
            'https://www.stopforumspam.com/api*' => Http::response([
                'success' => 1,
                'username' => ['frequency' => 0],
                'ip' => ['frequency' => 0, 'confidence' => 0],
                'email' => ['frequency' => 0, 'confidence' => 0],
            ]),
        ]);

        Livewire::test(Register::class)
            ->set('data.username', 'clean_user')
            ->set('data.name', 'Clean User')
            ->set('data.email', 'clean@example.com')
            ->set('data.password', 'Password123!')
            ->set('data.passwordConfirmation', 'Password123!')
            ->set('data.accept_privacy_policy', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'clean@example.com')->firstOrFail();

        $this->assertFalse($user->always_moderate_forum_content);
        $this->assertFalse($user->moderate_first_forum_post);
    }
}
