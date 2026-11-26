<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAuthEndpoints
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->resolveAttempts($request) as $bucket => $attempts) {
            $key = $this->throttleKey($request, $bucket);
            [$maxAttempts, $decaySeconds] = $this->limitsForBucket($bucket);

            // The first check also clears a count left from a closed window. The count that
            // comes back from adding to it is what holds when requests arrive together.
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)
                || RateLimiter::increment($key, $decaySeconds, $attempts) > $maxAttempts) {
                $retryAfter = RateLimiter::availableIn($key);

                return $this->tooManyAttemptsResponse($request, $bucket, $retryAfter);
            }
        }

        return $next($request);
    }

    /**
     * @return array<string, int> How many attempts the request makes, by bucket.
     */
    private function resolveAttempts(Request $request): array
    {
        if (strtoupper($request->method()) !== 'POST') {
            return [];
        }

        // Livewire's path carries a per-application hash, so match the route.
        if ($request->routeIs('*livewire.update')) {
            return $this->resolveLivewireAttempts($request);
        }

        // By route name, never by path: one route can be reached by more than one spelling of
        // its path.
        $bucket = match ($request->route()?->getName()) {
            'login.store', 'login.two-factor.store' => 'login',
            'register.store' => 'register',
            'password.email', 'password.update', 'verification.send' => 'password_reset',
            default => null,
        };

        return $bucket === null ? [] : [$bucket => 1];
    }

    /**
     * Counts the dashboard forms' submit calls, each one, since Livewire runs them all. Their
     * pages also post an update as fields change, and those must not use up the allowance.
     *
     * @return array<string, int>
     */
    private function resolveLivewireAttempts(Request $request): array
    {
        // The same input Livewire reads: it takes the query string as well as the body.
        $components = $request->input('components');
        if (! is_array($components)) {
            return [];
        }

        $attempts = [];

        foreach ($components as $component) {
            if (! is_array($component) || ! is_array($component['calls'] ?? null)) {
                continue;
            }

            foreach ($component['calls'] as $call) {
                $method = is_array($call) && is_string($call['method'] ?? null) ? strtolower(trim($call['method'])) : '';

                $bucket = match ($method) {
                    'authenticate' => 'login',
                    'register' => 'register',
                    // "request" is too common a method name to count on any other component.
                    'request' => $this->isComponent($component, RequestPasswordReset::class) ? 'password_reset' : null,
                    'resetpassword' => $this->isComponent($component, ResetPassword::class) ? 'password_reset' : null,
                    default => null,
                };

                if ($bucket !== null) {
                    $attempts[$bucket] = ($attempts[$bucket] ?? 0) + 1;
                }
            }
        }

        return $attempts;
    }

    /**
     * @param  array<mixed>  $component
     * @param  class-string  $class
     */
    private function isComponent(array $component, string $class): bool
    {
        $snapshot = is_string($component['snapshot'] ?? null) ? json_decode($component['snapshot'], true) : null;
        $name = is_array($snapshot) ? ($snapshot['memo']['name'] ?? null) : null;

        if (! is_string($name)) {
            return false;
        }

        $finder = app('livewire.finder');
        $resolved = $finder->resolveClassComponentClassName($finder->normalizeName($name));

        return is_string($resolved) && is_a($resolved, $class, true);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function limitsForBucket(string $bucket): array
    {
        return match ($bucket) {
            'login' => [10, 300],
            'register' => [4, 600],
            'password_reset' => [6, 600],
            default => [6, 300],
        };
    }

    private function throttleKey(Request $request, string $bucket): string
    {
        // Nothing the client can choose (the user agent, say) may go in the key, or changing
        // it would start a fresh allowance.
        $ip = (string) ($request->ip() ?? 'unknown-ip');

        return 'auth-throttle:'.$bucket.':'.sha1($ip);
    }

    private function tooManyAttemptsResponse(Request $request, string $bucket, int $retryAfter): Response
    {
        $message = __('auth.errors.throttle', [
            'bucket' => str_replace('_', ' ', $bucket),
            'seconds' => max(1, $retryAfter),
        ]);

        // Livewire shows a failed update's body to the visitor in a popup, which is dark
        // behind the document unless the document sets its own colours.
        if ($request->routeIs('*livewire.update')) {
            return response(sprintf(
                '<!doctype html><meta charset="utf-8"><body style="margin:0;padding:24px;background:#fff;color:#111;font:16px/1.5 sans-serif">%s</body>',
                e($message),
            ), 429);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'retry_after' => max(1, $retryAfter),
            ], 429);
        }

        return response($message, 429);
    }
}
