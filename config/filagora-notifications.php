<?php

declare(strict_types=1);

/*
 * Only what the site changes. Laravel merges a package's config one level deep, so a key that is
 * a list of settings (`routes`) must be given whole.
 */
return [

    // Serve the signed unsubscribe links (off by default), so the mail's one-click links work.
    'routes' => [
        'enabled' => true,
        'prefix' => 'email-preferences',
        'middleware' => ['throttle:60,1'],
    ],

    'unsubscribe' => [
        'signed_ttl' => null,
        'view' => 'filagora-notifications::unsubscribed',
        'manage_url' => '/'.env('SITE_PANEL_PATH', 'admin'),
    ],

];
