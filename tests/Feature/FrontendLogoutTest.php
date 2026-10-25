<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_logout_redirects_to_requested_internal_path(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout', [
            'redirect_to' => '/profile',
        ]);

        $response->assertRedirect('/profile');
        $this->assertGuest();
    }

    public function test_frontend_logout_ignores_external_redirect_targets(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout', [
            'redirect_to' => 'https://example.com',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_frontend_logout_ignores_scheme_relative_redirect_targets(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout', [
            'redirect_to' => '//evil.example/phish',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_frontend_logout_from_dashboard_referrer_redirects_to_home(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/dashboard')
            ->post('/logout');

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
