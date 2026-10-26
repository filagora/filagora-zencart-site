<?php

/*
 * Overrides of the plugins package's configuration. A top-level key replaces the package's whole
 * array, so each key listed here carries all of its own values.
 */
return [
    // Release archives are on the public disk, where the old site kept them.
    'storage' => [
        'disk' => env('FILAGORA_PLUGINS_DISK', 'public'),
    ],

    'routes' => [
        // The named routes of the plugin page and a forum thread, used in mail and in the API.
        'plugin' => 'plugins.show',
        'thread' => 'threads.show',

        // The package's JSON API and download routes stay off while the site's own pages serve
        // downloads (they redirect a guest to sign in rather than answer 401). The API needs
        // account tokens, which arrive with slice S5.
        'api' => [
            'enabled' => (bool) env('FILAGORA_PLUGINS_API_ENABLED', false),
            'prefix' => 'api/v1',
            'middleware' => ['api'],
            'guard' => null,
            'per_page' => 15,
            'max_upload_kb' => 102400,
            'keys' => [
                'read' => 'api.plugins.read',
                'download' => 'api.plugins.download',
                'write' => 'api.plugins.write',
            ],
        ],

        'downloads' => [
            'enabled' => false,
            'prefix' => 'plugins',
            'middleware' => ['web'],
        ],
    ],
];
