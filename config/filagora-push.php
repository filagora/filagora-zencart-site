<?php

declare(strict_types=1);

/*
 * Only what the site changes. The keys the browser push service signs with are in config/webpush.php
 * (VAPID_SUBJECT, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY).
 */
return [

    // The named pages an alert opens.
    'routes' => [
        'thread' => 'threads.show',
        'conversation' => 'messages.show',
    ],

];
