<?php

/*
 * Overrides of the messages package's configuration. A top-level key replaces the package's whole
 * array, so each key listed here carries all of its own values.
 */
return [
    // Off so the package does not mail every recipient regardless of their settings; the site's
    // MessageReceived listener (BellListeners) sends the same mail to people whose email choice for
    // new messages allows it. It links to the conversation through the route below.
    'mail' => [
        'enabled' => false,
        'routes' => ['conversation' => 'messages.show'],
    ],
];
