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

    // The mail links back to the thread. Each key replaces the package's whole array, so the
    // package's other values are carried here.
    'subscriptions' => [
        'user_attributes' => [
            'frequency' => 'thread_subscription_email_frequency',
            'auto_subscribe' => 'auto_subscribe_on_post',
        ],
        'auto_subscribe_default' => false,
        // The site sends its own thread-update and digest mail (App\Listeners\SendThreadSubscriptionMail).
        'mail' => ['enabled' => false],
        'routes' => ['thread' => 'threads.show'],
    ],

    'mentions' => [
        'enabled' => true,
        'user_attribute' => 'username',
        'max_per_post' => 10,
        // The old site told people about mentions with the bell and browser push only, never mail.
        'mail' => ['enabled' => false],
        'routes' => ['thread' => 'threads.show'],
    ],
];
