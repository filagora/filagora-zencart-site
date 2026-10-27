<?php

return [
    'alerts' => [
        // Comma-separated list in env supported, e.g. BACKUP_ALERT_EMAILS=ops@example.com,security@example.com
        'emails' => array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', (string) env('BACKUP_ALERT_EMAILS', ''))
        ))),
    ],

    'schedule' => [
        'enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),
        'daily_at' => (string) env('BACKUP_SCHEDULE_DAILY_AT', '01:30'),
        'clean_at' => (string) env('BACKUP_SCHEDULE_CLEAN_AT', '02:45'),
    ],

    // Max seconds RunDatabaseBackupJob may run before the queue worker kills
    // it. Production's fast egress to S3 finishes well within the default;
    // slower connections (e.g. local dev uploading a large dump over a home
    // connection) may need this raised.
    'job_timeout' => (int) env('BACKUP_JOB_TIMEOUT', 1200),
];
