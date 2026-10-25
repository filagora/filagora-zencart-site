<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A same-origin relative path safe to redirect back to after an auth flow -
 * shared sanitizing logic for LoginController, RegisterController and
 * EmailVerificationController, each of which captures it from a different
 * place (query string, hidden form field) with its own extra rules, and for
 * AuthRedirectResolver, which reads Laravel's own `url.intended` session
 * value - always an absolute URL, per Illuminate\Routing\Redirector::guest().
 */
final class IntendedPath
{
    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (Str::startsWith($value, '/') && ! Str::startsWith($value, '//')) {
            return $value;
        }

        return self::sameOriginPath($value);
    }

    private static function sameOriginPath(string $value): ?string
    {
        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);

        if ($appHost === null || strcasecmp($parts['host'], $appHost) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }
}
