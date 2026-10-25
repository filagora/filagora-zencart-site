<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration Email Approval
    |--------------------------------------------------------------------------
    |
    | When enabled, verification emails are sent only to approved email
    | addresses or domains. This lets production avoid sending paid email to
    | arbitrary addresses submitted through the registration form.
    |
    */

    'email_allowlist' => [
        'enabled' => env('REGISTRATION_EMAIL_ALLOWLIST_ENABLED', false),

        'addresses' => array_values(array_filter(array_map(
            static fn (string $address): string => strtolower(trim($address)),
            explode(',', (string) env('REGISTRATION_ALLOWED_EMAIL_ADDRESSES', ''))
        ))),

        'domains' => array_values(array_filter(array_map(
            static fn (string $domain): string => strtolower(ltrim(trim($domain), '@')),
            explode(',', (string) env('REGISTRATION_ALLOWED_EMAIL_DOMAINS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Email Daily Send Limit
    |--------------------------------------------------------------------------
    |
    | A hard, account-wide ceiling on verification emails sent per day
    | (covers both initial registration and resend), independent of Turnstile
    | and spam-detection accuracy. Protects the configured mail provider's
    | send quota from a distributed bot flood that evades those heuristics.
    | Per-IP registration throttling is already handled by Filament's
    | built-in Register page rate limiting.
    |
    */

    'verification_send_limit' => [
        'enabled' => env('REGISTRATION_VERIFICATION_SEND_LIMIT_ENABLED', true),
        'daily_max' => (int) env('REGISTRATION_VERIFICATION_SEND_DAILY_MAX', 300),
    ],

];
