<?php

/*
 * Overrides of the messages package's configuration. A top-level key replaces the package's whole
 * array, so each key listed here carries all of its own values.
 */
return [
    // The old site told people about new messages with the bell and browser push only, never mail.
    // The mail is built and links to the conversation if it is ever switched on.
    'mail' => [
        'enabled' => false,
        'routes' => ['conversation' => 'messages.show'],
    ],
];
