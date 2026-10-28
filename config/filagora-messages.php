<?php

/*
 * Overrides of the messages package's configuration. A top-level key replaces the package's whole
 * array, so each key listed here carries all of its own values.
 */
return [
    // The new-message mail links to the conversation.
    'mail' => [
        'enabled' => true,
        'routes' => ['conversation' => 'messages.show'],
    ],
];
