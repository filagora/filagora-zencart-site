<?php

/*
 * Overrides of the Zen Cart profile package's configuration. A top-level key replaces the package's
 * whole array, so each key listed here carries all of its own values.
 */
return [
    // How long shared caches (Cloudflare, the version server) may hold a version-check answer, empty
    // answers too. Production has served 1800 seconds through this variable, so its name is kept.
    'versioncheck' => [
        'routes' => true,
        'cache_ttl_seconds' => (int) env('PLUGINS_VERSIONCHECK_CACHE_TTL_SECONDS', 900),
        'plugin_route' => null,
        'thread_route' => null,
    ],
];
