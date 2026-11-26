<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleAuthEndpoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The router decodes a path before matching it, so each of these reaches the same endpoint as
     * its plain spelling and must count against the same allowance.
     */
    #[DataProvider('endpoints')]
    public function test_the_limit_holds_however_the_path_is_spelled(string $plain, string $encoded, int $allowed): void
    {
        for ($attempt = 1; $attempt <= $allowed; $attempt++) {
            $this->assertNotSame(429, $this->post($encoded)->getStatusCode(), "attempt {$attempt}");
        }

        $this->post($encoded)->assertTooManyRequests();
        $this->post($plain)->assertTooManyRequests();
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function endpoints(): array
    {
        return [
            'sign in' => ['/login', '/%6Cogin', 10],
            'two-factor code' => ['/login/two-factor', '/login/two%2Dfactor', 10],
            'registration' => ['/register', '/%72egister', 4],
            'reset link' => ['/password/forgot', '/%70assword/forgot', 6],
            'new password' => ['/password/reset', '/%70assword/reset', 6],
            'verification mail' => ['/email/verification-notification', '/%65mail/verification-notification', 6],
        ];
    }

    /**
     * A route put behind the middleware without a bucket would be let through unlimited.
     */
    public function test_every_public_route_behind_the_middleware_is_counted(): void
    {
        $routes = collect(Route::getRoutes())->filter(
            fn ($route): bool => in_array(ThrottleAuthEndpoints::class, $route->middleware(), true)
                && in_array('POST', $route->methods(), true)
                // The dashboard's panel carries it too, for its Livewire sign-in forms.
                && ! str_starts_with((string) $route->getName(), 'filament.')
                && ! str_starts_with((string) $route->uri(), 'livewire'),
        );

        $covered = array_map(fn (array $endpoint): string => ltrim($endpoint[0], '/'), array_values(self::endpoints()));

        $this->assertEqualsCanonicalizing($covered, $routes->map->uri()->values()->all());
    }
}
