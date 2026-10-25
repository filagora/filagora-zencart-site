<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAuthEndpoints
{
    public function handle(Request $request, Closure $next): Response
    {
        $bucket = $this->resolveBucket($request);
        if ($bucket === null) {
            return $next($request);
        }

        $key = $this->throttleKey($request, $bucket);
        [$maxAttempts, $decaySeconds] = $this->limitsForBucket($bucket);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return $this->tooManyAttemptsResponse($request, $bucket, $retryAfter);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }

    private function resolveBucket(Request $request): ?string
    {
        if (strtoupper($request->method()) !== 'POST') {
            return null;
        }

        $path = trim($request->path(), '/');

        return match (true) {
            $path === 'dashboard/login' => 'login',
            $path === 'dashboard/register' => 'register',
            str_starts_with($path, 'dashboard/password-reset') => 'password_reset',
            $path === 'login', $path === 'login/two-factor' => 'login',
            $path === 'register' => 'register',
            str_starts_with($path, 'password') => 'password_reset',
            $path === 'email/verification-notification' => 'password_reset',
            $path === 'livewire/update' => $this->resolveLivewireBucket($request),
            default => null,
        };
    }

    private function resolveLivewireBucket(Request $request): ?string
    {
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return null;
        }

        $components = $payload['components'] ?? [];
        if (! is_array($components)) {
            return null;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $snapshot = (string) ($component['snapshot'] ?? '');
            $calls = $component['calls'] ?? [];
            $methods = [];
            if (is_array($calls)) {
                foreach ($calls as $call) {
                    if (! is_array($call)) {
                        continue;
                    }
                    $method = strtolower(trim((string) ($call['method'] ?? '')));
                    if ($method !== '') {
                        $methods[] = $method;
                    }
                }
            }

            $isLoginComponent = str_contains($snapshot, 'filament.pages.auth.login');
            $isRegisterComponent = str_contains($snapshot, 'filament.pages.auth.register');

            if ($isLoginComponent || in_array('authenticate', $methods, true)) {
                return 'login';
            }

            if ($isRegisterComponent || in_array('register', $methods, true)) {
                return 'register';
            }
        }

        return null;
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
        $ip = (string) ($request->ip() ?? 'unknown-ip');
        $agent = strtolower(substr((string) ($request->userAgent() ?? 'unknown-agent'), 0, 120));

        return 'auth-throttle:'.$bucket.':'.sha1($ip.'|'.$agent);
    }

    private function tooManyAttemptsResponse(Request $request, string $bucket, int $retryAfter): Response
    {
        $message = __('auth.errors.throttle', [
            'bucket' => str_replace('_', ' ', $bucket),
            'seconds' => max(1, $retryAfter),
        ]);

        if ($request->expectsJson() || $request->is('livewire/*')) {
            return response()->json([
                'message' => $message,
                'retry_after' => max(1, $retryAfter),
            ], 429);
        }

        return response($message, 429);
    }
}
