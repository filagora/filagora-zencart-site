<?php

namespace App\Support;

use Filagora\Core\Settings\SettingsRegistry;

/**
 * Where the privacy and cookie policy pages are: whichever page the setting names, as a path
 * (starting with a slash) or as a page slug.
 */
class LegalPages
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function hasCookiePolicyPage(): bool
    {
        return trim($this->settings->string('cookie_policy_page', '/cookie-policy')) !== '';
    }

    public function cookiePolicyPageUrl(): string
    {
        return $this->resolve($this->settings->string('cookie_policy_page', '/cookie-policy'), '/cookie-policy');
    }

    public function privacyPageUrl(): string
    {
        return $this->resolve($this->settings->string('privacy_page', '/privacy'), '/privacy');
    }

    private function resolve(string $value, string $default): string
    {
        $reference = trim($value) === '' ? $default : trim($value);

        return str_starts_with($reference, '/') ? $reference : '/pages/'.$reference;
    }
}
