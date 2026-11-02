<?php

return [
    'enabled' => env('NFTABLES_ENABLED', false),

    'timeout' => (int) env('NFTABLES_TIMEOUT', 10),

    'note' => env('NFTABLES_NOTE', 'This page runs external nftables commands. Use a non-interactive wrapper or sudo rule that is safe for the web process.'),

    'commands' => [
        'ruleset' => env('NFTABLES_RULESET_COMMAND', ''),
        'remove_element' => env('NFTABLES_REMOVE_ELEMENT_COMMAND', ''),
    ],
];
