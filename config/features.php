<?php

return [
    // Threads/Posts totals boxes shown at the bottom of a forum's thread list.
    // Off by default — set FEATURE_FORUM_TOTALS_WIDGET=true to turn it on.
    'forum_totals_widget' => env('FEATURE_FORUM_TOTALS_WIDGET', false),

    // "Page N of M" text shown alongside thread-list pagination controls.
    // Off by default — set FEATURE_THREAD_PAGINATION_SUMMARY=true to turn it on.
    'thread_pagination_summary' => env('FEATURE_THREAD_PAGINATION_SUMMARY', false),

    // Like button (and like counts) on forum posts, plus the toggle endpoint behind it.
    // Off by default — set FEATURE_POST_LIKES=true to turn it on.
    'post_likes' => env('FEATURE_POST_LIKES', false),
];
