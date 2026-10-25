<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_visiting_the_prompt_is_redirected_to_login(): void
    {
        $this->get(route('verification.notice'))
            ->assertRedirect(route('login'));
    }

    public function test_already_verified_user_visiting_the_prompt_is_redirected_home(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertRedirect(route('home'));
    }

    public function test_unverified_user_sees_the_prompt(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verify Your Email');
    }

    public function test_visiting_the_prompt_with_an_intended_path_stores_it_in_session(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice', ['intended' => '/profile']))
            ->assertOk();

        $this->assertSame('/profile', session('url.intended'));
    }

    public function test_resend_sends_a_new_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_does_nothing_for_an_already_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('verification.send'));

        Notification::assertNothingSent();
    }

    public function test_resend_is_rate_limited_after_two_attempts(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'));
        $this->actingAs($user)->post(route('verification.send'));

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertSessionHasErrors(['resend']);
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_guest_cannot_trigger_a_resend(): void
    {
        $this->post(route('verification.send'))->assertForbidden();
    }
}
