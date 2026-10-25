<?php

return [
    // Cache TTL for /forums index metadata payloads (seconds).
    'index_cache_ttl_seconds' => env('FORUMS_INDEX_CACHE_TTL_SECONDS', 300),

    // Cache TTL for the post count shown on a member profile (seconds).
    'member_post_count_cache_ttl_seconds' => env('MEMBER_POST_COUNT_CACHE_TTL_SECONDS', 300),

    // Verbose per-stage tracing for the reply/post-save/search-sync path. Off by default.
    'trace_logging_enabled' => env('FORUM_TRACE_LOGGING_ENABLED', false),
];
