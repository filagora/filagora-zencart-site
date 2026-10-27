<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Newsletter Delivery
    |--------------------------------------------------------------------------
    |
    | Newsletters own subscriber consent and campaign state in the app.
    | NEWSLETTER_DELIVERY_PROVIDER is the single, canonical setting for
    | outbound newsletter delivery: it both (a) selects which Laravel mailer
    | (config/mail.php) newsletter campaign mail is routed through, by name,
    | and (b) determines which outbound tagging (X-Mailtrap-Variables vs
    | X-Mailgun-Variables) gets attached, so inbound webhook events
    | (delivery/bounce/complaint/click) are attributed to the right
    | NewsletterSend. Because of (a), this must name a mailer that actually
    | exists in config/mail.php — see docs/mailtrap-integration.md.
    |
    */

    'delivery' => [
        'provider' => env('NEWSLETTER_DELIVERY_PROVIDER', 'mailtrap'),
        'campaign_daily_limit' => (int) env('NEWSLETTER_CAMPAIGN_DAILY_LIMIT', 90),
    ],
];
