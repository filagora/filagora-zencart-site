<?php

use App\Notifications\SecurityMailNotification;

return [
    // The package decides who is told about a scan, a hold, a finding or an AI review; the site's
    // class gives the mail lara-forum's words and layout. Config merging is shallow, so `enabled` is
    // repeated here.
    'mail' => [
        'enabled' => true,
        'notification' => SecurityMailNotification::class,
    ],
];
