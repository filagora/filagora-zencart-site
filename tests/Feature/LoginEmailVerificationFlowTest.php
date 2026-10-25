<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginEmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_redirected_to_frontend_verify_flow_from_dashboard_prompt(): void
    {
        $user = User::factory()->staff()->unverified()->create();

        $response = $this->actingAs($user)
            ->withSession(['url.intended' => '/profile'])
            ->get('/dashboard/email-verification/prompt');

        $response->assertRedirect(route('verification.notice', ['intended' => '/profile']));
    }

    public function test_unverified_users_ajax_requests_do_not_clobber_the_sessions_intended_path(): void
    {
        $this->markTestSkipped('Needs /profile/cookie-consent: slice S5 (account).');

        // Regression test: the cookie-consent widget's JS polls
        // GET /profile/cookie-consent (Accept: application/json) on every
        // page load for any authenticated user, unverified or not. That
        // request used to fall through EnsureFrontendEmailIsVerified's
        // unverified-email branch the same as a real page navigation,
        // overwriting session `url.intended` with the AJAX endpoint's own
        // path. Verifying the email would then redirect the user's browser
        // to that clobbered JSON endpoint instead of back to the page they
        // actually meant to return to.
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->withSession(['url.intended' => '/profile?tab=details'])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/profile/cookie-consent');

        $response->assertStatus(403);
        $response->assertJson(['message' => __('api.auth.email_verification_required')]);
        $this->assertSame('/profile?tab=details', session('url.intended'));
    }
}
