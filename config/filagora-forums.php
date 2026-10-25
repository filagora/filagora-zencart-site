<?php

/*
 * Overrides of the forums package's configuration. A top-level key replaces the package's whole
 * array, so each key listed here carries all of its own values.
 */
return [
    'read_tracking' => [
        'baseline_attribute' => 'forum_read_baseline_at',
        // How far back /forums/unread looks. forum_read_baseline_at came from the old forum's last
        // visit, so dormant accounts carry baselines over a decade old; without a floor they match
        // nearly every thread ever posted, which is unusable and a full table scan.
        'unread_max_lookback_days' => env('FORUM_UNREAD_MAX_LOOKBACK_DAYS', 365),
    ],
];
