<?php

return [

    'enabled' => env('SPAM_DETECTION_ENABLED', false),

    'actions' => [
        'reject_spam_registrations' => env('SPAM_REJECT_REGISTRATIONS', true),
        'moderate_suspicious_registrations' => env('SPAM_MODERATE_SUSPICIOUS_REGISTRATIONS', true),
    ],

    'blocked_email_patterns' => array_values(array_filter(array_map(
        static fn (string $pattern): string => trim($pattern),
        explode(',', (string) env('SPAM_BLOCKED_EMAIL_PATTERNS', '.*@sina\.com$,.*@yeah\.com$,.*@gmx\.com$,.*@gmx\.us$,.*@.*:.*$'))
    ))),

    'project_honeypot' => [
        'enabled' => env('SPAM_PROJECT_HONEYPOT_ENABLED', false),
        'api_key' => env('SPAM_PROJECT_HONEYPOT_API_KEY'),
        'lookup_domain' => env('SPAM_PROJECT_HONEYPOT_LOOKUP_DOMAIN', 'dnsbl.httpbl.org'),
        'max_last_seen_days' => (int) env('SPAM_PROJECT_HONEYPOT_MAX_LAST_SEEN_DAYS', 21),
        'suspicious_visitor_min' => (int) env('SPAM_PROJECT_HONEYPOT_SUSPICIOUS_VISITOR_MIN', 1),
        'spammer_visitor_min' => (int) env('SPAM_PROJECT_HONEYPOT_SPAMMER_VISITOR_MIN', 4),
    ],

    'stop_forum_spam' => [
        'enabled' => env('SPAM_STOP_FORUM_SPAM_ENABLED', true),
        'url' => env('SPAM_STOP_FORUM_SPAM_URL', 'https://www.stopforumspam.com/api'),
        'confidence_threshold' => (int) env('SPAM_STOP_FORUM_SPAM_CONFIDENCE_THRESHOLD', 40),
        'frequency_threshold' => (int) env('SPAM_STOP_FORUM_SPAM_FREQUENCY_THRESHOLD', 1),
    ],

    'temporary_email' => [
        'enabled' => env('SPAM_TEMPORARY_EMAIL_ENABLED', false),
        'url' => env('SPAM_TEMPORARY_EMAIL_URL', 'https://api.usechecker.com/v1/check'),
        'api_key' => env('SPAM_TEMPORARY_EMAIL_API_KEY'),
    ],

];
