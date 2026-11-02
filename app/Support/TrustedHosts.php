<?php

namespace App\Support;

/**
 * The hostnames this app answers to, as patterns for Laravel's TrustHosts
 * middleware. Canonical links, structured data, the sitemap and emails are
 * built from the request's Host header, so an unexpected Host (a direct-to-IP
 * request with a forged header, say) could otherwise put its own hostname in
 * those URLs.
 *
 * Opt-in on purpose: with APP_TRUSTED_HOSTS empty nothing is enforced, because
 * a hostname that is missing from the list gets a 400. List every host nginx
 * passes to PHP (see docs/deployment.md, "Trusted hosts").
 */
class TrustedHosts
{
    /**
     * @return list<string> Anchored regular expressions, or [] to trust any host.
     */
    public static function patterns(): array
    {
        $configured = (string) config('app.trusted_hosts', '');

        return collect(explode(',', $configured))
            ->map(fn (string $host): string => strtolower(trim($host)))
            ->filter(fn (string $host): bool => $host !== '' && preg_match('/^[a-z0-9.-]+$/', $host) === 1)
            ->map(fn (string $host): string => '^'.preg_quote($host, '/').'$')
            ->values()
            ->all();
    }
}
