<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard's auth forms submit through Livewire's update route, so the auth limits are only
 * real if they hold on that route. These tests post to it as a browser does.
 */
class DashboardAuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sign_in_submissions_are_limited(): void
    {
        $snapshot = $this->snapshotOf('/dashboard/login');

        foreach (range(1, 10) as $attempt) {
            $this->livewireUpdate($snapshot, 'authenticate')->assertOk();
        }

        $this->livewireUpdate($snapshot, 'authenticate')
            ->assertTooManyRequests()
            ->assertSeeText('Too many login attempts.');
    }

    public function test_dashboard_registration_submissions_are_limited(): void
    {
        $snapshot = $this->snapshotOf('/dashboard/register');

        foreach (range(1, 4) as $attempt) {
            $this->livewireUpdate($snapshot, 'register')->assertOk();
        }

        $this->livewireUpdate($snapshot, 'register')
            ->assertTooManyRequests()
            ->assertSeeText('Too many register attempts.');
    }

    public function test_a_submission_sent_in_the_query_string_is_counted(): void
    {
        // Livewire reads its payload from the query string as well as the body.
        $query = http_build_query(['components' => [[
            'snapshot' => $this->snapshotOf('/dashboard/login'),
            'updates' => ['data.username' => 'someone'],
            'calls' => [['method' => 'authenticate', 'params' => ['']]],
        ]]]);
        $send = fn (): TestResponse => $this->withHeader('X-Livewire', '1')
            ->postJson(Livewire::getUpdateUri().'?'.$query, []);

        foreach (range(1, 10) as $attempt) {
            $send()->assertOk();
        }

        $send()->assertTooManyRequests();
    }

    public function test_every_submit_call_in_a_request_is_counted(): void
    {
        $snapshot = $this->snapshotOf('/dashboard/login');

        $this->livewireUpdate($snapshot, ...array_fill(0, 11, 'authenticate'))->assertTooManyRequests();
    }

    public function test_one_request_is_charged_to_each_limit_it_uses(): void
    {
        $login = $this->snapshotOf('/dashboard/login');

        $this->livewireUpdate($login, 'authenticate', ...array_fill(0, 5, 'register'))
            ->assertTooManyRequests()
            ->assertSeeText('Too many register attempts.');
    }

    public function test_dashboard_reset_link_requests_count_towards_the_password_reset_limit(): void
    {
        $snapshot = $this->snapshotOf('/dashboard/password-reset/request');

        foreach (range(1, 6) as $attempt) {
            $this->livewireUpdate($snapshot, 'request')->assertOk();
        }

        $this->livewireUpdate($snapshot, 'request')
            ->assertTooManyRequests()
            ->assertSeeText('Too many password reset attempts.');
    }

    public function test_dashboard_new_password_submissions_count_towards_the_password_reset_limit(): void
    {
        $user = User::factory()->create();
        $url = Filament::getPanel('dashboard')->getResetPasswordUrl('a-token', $user);
        $snapshot = $this->snapshotOf($url);

        foreach (range(1, 6) as $attempt) {
            $this->livewireUpdate($snapshot, 'resetPassword')->assertOk();
        }

        $this->livewireUpdate($snapshot, 'resetPassword')->assertTooManyRequests();
    }

    public function test_requests_that_arrive_together_cannot_all_slip_under_the_limit(): void
    {
        // What parallel requests see: each reads the count before any of them has added to it.
        $this->app->instance(RateLimiter::class, new class($this->app->make('cache')->store()) extends RateLimiter
        {
            public function tooManyAttempts($key, $maxAttempts): bool
            {
                return false;
            }
        });
        RateLimiterFacade::clearResolvedInstance(RateLimiter::class);
        $snapshot = $this->snapshotOf('/dashboard/register');

        foreach (range(1, 4) as $attempt) {
            $this->livewireUpdate($snapshot, 'register')->assertOk();
        }

        $this->livewireUpdate($snapshot, 'register')->assertTooManyRequests();
    }

    public function test_updates_that_do_not_submit_the_form_are_not_counted(): void
    {
        $login = $this->snapshotOf('/dashboard/login');
        $register = $this->snapshotOf('/dashboard/register');

        // The username field updates the page whenever it loses focus.
        foreach (range(1, 15) as $update) {
            $this->livewireUpdate($login)->assertOk();
            $this->livewireUpdate($register)->assertOk();
        }
    }

    private function snapshotOf(string $path): string
    {
        $html = (string) $this->get($path)->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);

            if (str_contains((string) json_decode($snapshot, true)['memo']['name'], 'Auth')) {
                return $snapshot;
            }
        }

        $this->fail("No auth page snapshot found on {$path}.");
    }

    private function livewireUpdate(string $snapshot, string ...$methods): TestResponse
    {
        return $this->withHeader('X-Livewire', '1')->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) [],
                'calls' => array_map(
                    static fn (string $method): array => ['method' => $method, 'params' => [], 'metadata' => (object) []],
                    array_values($methods),
                ),
            ]],
        ]);
    }
}
