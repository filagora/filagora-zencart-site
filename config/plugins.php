<?php

return [
    // Kill switch for new plugins and releases (PLUGINS_SUBMISSIONS_ENABLED=false). Browsing,
    // downloads and the version check are unaffected.
    'submissions_enabled' => (bool) env('PLUGINS_SUBMISSIONS_ENABLED', true),

    // Cache TTL for the plugin library's public pages (seconds). The cache is also dropped whenever
    // a plugin, release, type or Zen Cart version changes.
    'index_cache_ttl_seconds' => env('PLUGINS_INDEX_CACHE_TTL_SECONDS', 300),
];
