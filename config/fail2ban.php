<?php

return [
    'enabled' => env('FAIL2BAN_ENABLED', false),

    'timeout' => (int) env('FAIL2BAN_TIMEOUT', 10),

    'note' => env('FAIL2BAN_NOTE', 'This page runs external fail2ban commands. Use a non-interactive wrapper or sudo rule that is safe for the web process.'),

    'commands' => [
        'status' => env('FAIL2BAN_STATUS_COMMAND', ''),
        'jail_status' => env('FAIL2BAN_JAIL_STATUS_COMMAND', ''),
        'unban' => env('FAIL2BAN_UNBAN_COMMAND', ''),
    ],
];
