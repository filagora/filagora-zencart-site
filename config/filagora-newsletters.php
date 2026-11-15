<?php

use App\Filament\RichEditor\Plugins\InsertMediaLibraryImagePlugin;

/*
 * Overrides of the newsletters package's configuration. A top-level key replaces the package's
 * whole array, so each key listed here carries all of its own values.
 */
return [
    // What the newsletter is called in the mail and pages the package sends.
    'name' => 'Zen Cart newsletter',

    // The words stored with each subscription, and their version labels.
    'consent' => [
        'profile_text' => null,
        'profile_version' => 'profile-v1',
        'public_text' => null,
        'public_version' => 'public-v1',
    ],

    // The package serves the signup page and the signed links; the pages themselves are the
    // site's own, in its layout.
    'routes' => [
        'enabled' => true,
        'prefix' => 'newsletter',
        'middleware' => ['web'],
        'signup_per_minute' => 5,
        'views' => [
            'show' => 'newsletter.show',
            'confirmed' => 'newsletter.confirmed',
            'unsubscribed' => 'newsletter.unsubscribed',
        ],
    ],

    // Campaign mail goes through the mailer named here (it must exist in config/mail.php), and
    // its provider's delivery events are matched to the send. The one canonical setting, see
    // docs/mailtrap-integration.md.
    'delivery' => [
        'provider' => env('NEWSLETTER_DELIVERY_PROVIDER', 'mailtrap'),
        'campaign_daily_limit' => (int) env('NEWSLETTER_CAMPAIGN_DAILY_LIMIT', 90),
        'queue' => 'mail',
    ],

    // What the shared header and footer say until an editor saves their own in the settings.
    'branding' => [
        'brand_name' => 'Zen Cart',
        'header_eyebrow' => 'Zen Cart Newsletter',
        'logo_alt' => 'Zen Cart',
        'footer_text' => 'You are receiving this because you subscribed to the Zen Cart newsletter.',
        'manage_link_label' => 'Unsubscribe or manage newsletter signup',
    ],

    // Staff only, signed in, with a verified address.
    'preview' => [
        'enabled' => true,
        'prefix' => 'staff/newsletters',
        'middleware' => ['web', 'auth', 'frontend.verified'],
    ],

    // The media library's image button, beside the package's tracked-link button.
    'editor' => [
        'plugins' => [InsertMediaLibraryImagePlugin::class],
        'toolbar_buttons' => ['insertMediaLibraryImage'],
    ],

    'schedule' => [
        'enabled' => true,
    ],
];
