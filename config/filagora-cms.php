<?php

/*
 * Overrides of the CMS package's configuration. A top-level key replaces the package's whole
 * array, so `preview` carries all of its own values.
 */
return [
    'preview' => [
        'uncacheable_middleware' => true,
        'share_route' => 'cms.preview.share.open',
        'query_parameter' => 'preview',
        'carry_token' => true,
        // A preview token follows links to other pages of the site, but not into the staff area,
        // the account pages or sign-out.
        'carry_token_except' => ['/staff', '/'.env('SITE_PANEL_PATH', 'dashboard'), '/login', '/logout', '/register', '/profile', '/preview'],
    ],
];
