<?php

use NotificationChannels\WebPush\PushSubscription;

return [

    /**
     * These are the keys for authentication (VAPID).
     * These keys must be safely stored and should not change.
     */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /**
     * This is model that will be used to for push subscriptions.
     */
    'model' => PushSubscription::class,

    /**
     * This is the name of the table that will be created by the migration and
     * used by the PushSubscription model shipped with this package.
     */
    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    /**
     * This is the database connection that will be used by the migration and
     * the PushSubscription model shipped with this package.
     */
    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /**
     * The HTTP client options used to deliver push notifications.
     */
    'client_options' => [],

    /**
     * Host suffixes a browser push `endpoint` is allowed to point at. When a
     * notification fires, minishlink/web-push issues an outbound POST to the
     * stored endpoint, so an unconstrained value is a server-side request
     * forgery vector (an authenticated user could register an internal host or
     * a cloud metadata URL). Every mainstream browser's push service is on one
     * of these; a `*.` entry matches that host and any sub-domain of it.
     *
     * @var list<string>
     */
    'allowed_endpoint_hosts' => [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        '*.push.services.mozilla.com',
        '*.notify.windows.com',
        'web.push.apple.com',
        '*.push.apple.com',
    ],

    /**
     * The automatic padding in bytes used by Minishlink\WebPush.
     * Set to false to support Firefox Android with v1 endpoint.
     */
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
