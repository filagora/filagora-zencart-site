<?php

/*
 * Overrides of the plugins package's configuration. A top-level key replaces the package's whole
 * array, so each key listed here carries all of its own values.
 */
return [
    // The same kill switch the site's pages read (config/plugins.php).
    'submissions_enabled' => (bool) env('PLUGINS_SUBMISSIONS_ENABLED', true),

    // Release archives are on the public disk, where the old site kept them.
    'storage' => [
        'disk' => env('FILAGORA_PLUGINS_DISK', 'public'),
    ],

    // The site sends its own plugin mail (App\Listeners\SendPluginMail), so the package's is off;
    // the support address and the author's receipt are still read from here.
    'mail' => [
        'enabled' => false,
        'support_email' => env('FILAGORA_PLUGINS_SUPPORT_EMAIL'),
        'acknowledge_submissions' => true,
    ],

    'routes' => [
        // The named routes of the plugin page and a forum thread, used in mail and in the API.
        'plugin' => 'plugins.show',
        'thread' => 'threads.show',

        // The JSON API is on, signed in by account API keys (Sanctum). The package's download routes
        // stay off: the site's own pages serve downloads (they redirect a guest to sign in rather
        // than answer 401).
        'api' => [
            'enabled' => (bool) env('FILAGORA_PLUGINS_API_ENABLED', true),
            'prefix' => 'api/v1',
            'middleware' => ['api'],
            'guard' => 'sanctum',
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
