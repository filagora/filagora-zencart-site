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
    // The Zen Cart documentation the AI chat reads (`docs:import`): a local checkout if
    // ZENCART_DOCS_PATH is set, else a fresh clone. `base_url` is where the pages are published,
    // for the links the chat cites. The profile has no default for it; this site's is its own.
    'docs' => [
        'path' => env('ZENCART_DOCS_PATH'),
        'repo_url' => env('ZENCART_DOCS_REPO_URL') ?: 'https://github.com/zencart/documentation.git',
        'ref' => env('ZENCART_DOCS_REF') ?: 'master',
        'base_url' => env('LEGACY_DOCS_URL') ?: 'https://docs.zen-cart.com',
    ],
];
